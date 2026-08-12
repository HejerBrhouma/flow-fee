<?php

namespace App\EventListener;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Intercepts a successful email+password login (json_login) for accounts with TOTP 2FA
 * enabled. Rather than issuing the real JWT immediately, this swaps the response for a
 * short-lived challenge token — the real JWT is only ever created by
 * TwoFactorController::verify() once the TOTP code checks out. This keeps 2FA verification
 * entirely out of band from the JWT itself, so there's no "partially authenticated" token
 * that some other listener has to remember to block on every subsequent request.
 */
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS, method: 'onAuthenticationSuccess')]
class TwoFactorLoginListener
{
    private const CHALLENGE_TTL = 300; // 5 minutes to enter the code

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
    ) {}

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User || !$user->isTwoFactorEnabled()) {
            return;
        }

        $challengeToken = bin2hex(random_bytes(32));
        $item = $this->cache->getItem('2fa_challenge_' . $challengeToken);
        $item->set($user->getId());
        $item->expiresAfter(self::CHALLENGE_TTL);
        $this->cache->save($item);

        $event->setData([
            'twoFactorRequired' => true,
            'challengeToken' => $challengeToken,
        ]);
    }
}
