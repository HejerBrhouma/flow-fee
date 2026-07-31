<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\FacebookUser;
use League\OAuth2\Client\Provider\GoogleUser;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class OAuthAuthenticator extends OAuth2Authenticator
{
    // Must match the custom URL scheme registered in the mobile app (Info.plist / AndroidManifest.xml)
    private const MOBILE_APP_SCHEME = 'com.flowfee.app';

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {}

    public function supports(Request $request): ?bool
    {
        return in_array($request->attributes->get('_route'), [
            'api_auth_oauth_check_google',
            'api_auth_oauth_check_facebook',
            'api_auth_oauth_check_google_mobile',
            'api_auth_oauth_check_facebook_mobile',
        ]);
    }

    public function authenticate(Request $request): Passport
    {
        $route = $request->attributes->get('_route');
        $provider = str_contains($route, 'google') ? 'google' : 'facebook';
        $clientName = str_contains($route, 'mobile') ? "{$provider}_mobile" : $provider;
        $client = $this->clientRegistry->getClient($clientName);

        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $provider) {
                $oauthUser = $client->fetchUserFromToken($accessToken);

                if ($provider === 'google') {
                    /** @var GoogleUser $oauthUser */
                    return $this->handleGoogleUser($oauthUser);
                }

                /** @var FacebookUser $oauthUser */
                return $this->handleFacebookUser($oauthUser);
            })
        );
    }

    private function handleGoogleUser(GoogleUser $oauthUser): User
    {
        $user = $this->userRepository->findOneBy(['googleId' => $oauthUser->getId()])
            ?? $this->userRepository->findOneBy(['email' => $oauthUser->getEmail()]);

        if (!$user) {
            $user = new User();
            $user->setEmail($oauthUser->getEmail());
            $user->setFirstName($oauthUser->getFirstName() ?? '');
            $user->setLastName($oauthUser->getLastName() ?? '');
            $user->setAvatar($oauthUser->getAvatar());
            $user->setIsVerified(true);
        }

        $user->setGoogleId($oauthUser->getId());
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function handleFacebookUser(FacebookUser $oauthUser): User
    {
        $user = $this->userRepository->findOneBy(['facebookId' => $oauthUser->getId()])
            ?? $this->userRepository->findOneBy(['email' => $oauthUser->getEmail()]);

        if (!$user) {
            $user = new User();
            $user->setEmail($oauthUser->getEmail() ?? '');
            $nameParts = explode(' ', $oauthUser->getName() ?? '', 2);
            $user->setFirstName($nameParts[0] ?? '');
            $user->setLastName($nameParts[1] ?? '');
            $user->setIsVerified(true);
        }

        $user->setFacebookId($oauthUser->getId());
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        /** @var User $user */
        $user = $token->getUser();
        $jwt = $this->jwtManager->create($user);

        $route = (string) $request->attributes->get('_route');

        if (str_contains($route, 'mobile')) {
            // The native app registers this custom URL scheme and catches the redirect
            // via Capacitor's App.addListener('appUrlOpen', ...) to resume the session.
            return new RedirectResponse(self::MOBILE_APP_SCHEME . '://oauth-callback?token=' . urlencode($jwt));
        }

        // Web flow: this response is only ever loaded inside the popup window opened by
        // AuthService.loginWithGoogle()/loginWithFacebook(), so postMessage to the opener works.
        return new Response(
            "<script>window.opener.postMessage({token: '{$jwt}'}, '*'); window.close();</script>",
            Response::HTTP_OK,
            ['Content-Type' => 'text/html']
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse([
            'message' => 'Authentification OAuth échouée.',
            'error' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
