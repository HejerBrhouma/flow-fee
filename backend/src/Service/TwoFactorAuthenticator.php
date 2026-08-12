<?php

namespace App\Service;

use App\Entity\User;
use OTPHP\TOTP;

/**
 * Thin wrapper around spomky-labs/otphp so the rest of the app never touches the library
 * directly. TOTP (not email OTP) was chosen because the project's mailer isn't configured
 * (no real MAILER_DSN) — TOTP needs nothing external, just an authenticator app.
 */
class TwoFactorAuthenticator
{
    public function generateSecret(): string
    {
        return TOTP::generate()->getSecret();
    }

    public function getProvisioningUri(User $user, string $secret): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($user->getEmail());
        $totp->setIssuer('Flow Fee');

        return $totp->getProvisioningUri();
    }

    /**
     * @param int $window number of 30s steps of tolerance on either side, to absorb clock drift
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        return TOTP::createFromSecret($secret)->verify($code, null, $window);
    }
}
