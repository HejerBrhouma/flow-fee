<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Expenses, budgets and savings goals each carry their own currency, so summing raw
 * amounts across a user's expenses (dashboard totals, budget consumption, monthly trend)
 * silently mixed EUR, USD, GBP and TND as if they were the same unit. This converts to a
 * single currency before summing, using live rates from a free, no-API-key exchange rate
 * service — cached for a few hours since exact real-time precision isn't needed for a
 * spending overview, with a static approximate fallback if that service is unreachable.
 */
class CurrencyConverter
{
    private const FALLBACK_RATES_TO_EUR = [
        'EUR' => 1.0,
        'USD' => 0.92,
        'GBP' => 1.16,
        'TND' => 0.30,
    ];

    private const CACHE_TTL = 43200; // 12h

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    public function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        $rates = $this->ratesToEur();
        $fromRate = $rates[$from] ?? 1.0;
        $toRate = $rates[$to] ?? 1.0;

        return $amount * $fromRate / $toRate;
    }

    /**
     * @return array<string, float> units of EUR per 1 unit of each currency
     */
    private function ratesToEur(): array
    {
        try {
            return $this->cache->get('currency_rates_to_eur', function (ItemInterface $item): array {
                $item->expiresAfter(self::CACHE_TTL);

                // Free, no API key required; returns 1 EUR expressed in every other currency.
                $response = $this->httpClient->request('GET', 'https://open.er-api.com/v6/latest/EUR', [
                    'timeout' => 5,
                ]);
                $data = $response->toArray();

                if (($data['result'] ?? null) !== 'success' || empty($data['rates'])) {
                    throw new \RuntimeException('Unexpected exchange rate API response.');
                }

                // The API gives "1 EUR = X currency"; we want "1 currency = ? EUR", so invert.
                $ratesToEur = [];
                foreach (self::FALLBACK_RATES_TO_EUR as $currency => $fallback) {
                    $ratesToEur[$currency] = !empty($data['rates'][$currency])
                        ? 1 / $data['rates'][$currency]
                        : $fallback;
                }

                return $ratesToEur;
            });
        } catch (\Throwable $e) {
            $this->logger->warning('Exchange rate API unavailable, using approximate static rates instead.', [
                'exception' => $e->getMessage(),
            ]);

            return self::FALLBACK_RATES_TO_EUR;
        }
    }
}
