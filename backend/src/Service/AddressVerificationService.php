<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Checks that a (country, city, zip code) triple entered on company setup actually exists,
 * using OpenStreetMap's Nominatim (free, no API key, and unlike zippopotam.us it has real
 * coverage of Tunisia).
 *
 * We only pass postalcode+country to Nominatim, never city: its structured search silently
 * drops a constraint it can't satisfy instead of returning zero results (e.g.
 * postalcode=75001&city=Marseille still returns Marseille's boundary, ignoring the postal
 * code). Querying by postalcode+country alone reliably returns [] for a code that doesn't
 * exist in that country, and otherwise returns the one place that code belongs to — we then
 * check the requested city name appears somewhere in that place's address breakdown
 * (excluding the country field itself, e.g. Tunisia has no admin level literally called
 * "city" in OSM — a postcode belongs to a "Gouvernorat <City>", so we match against the
 * whole address minus the country to catch that without matching on the country name).
 */
class AddressVerificationService
{
    private const CACHE_TTL = 2592000; // 30 days: postal codes basically never move

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return bool|null true/false if verified, null if the lookup service was unreachable
     *                    (caller should then not block the user on an unverifiable address)
     */
    public function exists(string $country, string $city, string $zipCode): ?bool
    {
        $country = trim($country);
        $city = trim($city);
        $zipCode = trim($zipCode);

        if ($country === '' || $city === '' || $zipCode === '') {
            return false;
        }

        $cacheKey = 'addr_verify_' . md5(mb_strtolower("$country|$zipCode"));

        try {
            $results = $this->cache->get($cacheKey, function (ItemInterface $item) use ($country, $zipCode): array {
                $item->expiresAfter(self::CACHE_TTL);

                $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/search', [
                    'query' => [
                        'country' => $country,
                        'postalcode' => $zipCode,
                        'format' => 'json',
                        'addressdetails' => 1,
                        'limit' => 5,
                        'accept-language' => 'fr',
                    ],
                    'headers' => [
                        // Nominatim's usage policy requires an identifying User-Agent.
                        'User-Agent' => 'FlowFee/1.0 (contact: hejerbnrhouma@gmail.com)',
                    ],
                    'timeout' => 5,
                ]);

                return $response->toArray();
            });

            $needle = $this->normalize($city);

            foreach ($results as $result) {
                $address = $result['address'] ?? [];
                unset($address['country'], $address['country_code']);
                $haystack = $this->normalize(implode(' ', $address));

                if ($needle !== '' && str_contains($haystack, $needle)) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            $this->logger->warning('Address verification API unavailable.', [
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function normalize(string $s): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;

        return mb_strtolower(trim($transliterated));
    }
}
