<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\TwoFactorAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth/2fa', name: 'api_auth_2fa_')]
class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TwoFactorAuthenticator $twoFactorAuthenticator,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
    ) {}

    /**
     * Generates a new (not-yet-active) TOTP secret. 2FA stays off until /enable confirms the
     * user actually copied it into an authenticator app — calling this again before that just
     * overwrites the pending secret, so an abandoned setup has no effect on login.
     */
    #[Route('/setup', name: 'setup', methods: ['POST'])]
    public function setup(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $secret = $this->twoFactorAuthenticator->generateSecret();
        $user->setTotpSecret($secret);
        $this->em->flush();

        return $this->json([
            'secret' => $secret,
            'provisioningUri' => $this->twoFactorAuthenticator->getProvisioningUri($user, $secret),
        ]);
    }

    #[Route('/enable', name: 'enable', methods: ['POST'])]
    public function enable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        if (!$user->getTotpSecret()) {
            return $this->json(['message' => 'Aucune configuration en attente. Relancez la mise en place.'], Response::HTTP_CONFLICT);
        }

        if (!$this->twoFactorAuthenticator->verify($user->getTotpSecret(), (string) ($data['code'] ?? ''))) {
            return $this->json(['message' => 'Code invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setTwoFactorEnabled(true);
        $this->em->flush();

        return $this->json(['message' => 'Double authentification activée.']);
    }

    #[Route('/disable', name: 'disable', methods: ['POST'])]
    public function disable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        if ($user->getPassword() && !$this->passwordHasher->isPasswordValid($user, $data['password'] ?? '')) {
            return $this->json(['message' => 'Mot de passe incorrect.'], Response::HTTP_UNAUTHORIZED);
        }

        $user->setTwoFactorEnabled(false);
        $user->setTotpSecret(null);
        $this->em->flush();

        return $this->json(['message' => 'Double authentification désactivée.']);
    }

    /**
     * Public (no JWT yet): exchanges a login challenge token + TOTP code for the real JWT.
     * See TwoFactorLoginListener — the challenge token is issued instead of a token by the
     * login success handler whenever the account has 2FA enabled.
     */
    #[Route('/verify', name: 'verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $challengeToken = (string) ($data['challengeToken'] ?? '');
        $code = (string) ($data['code'] ?? '');

        $cacheKey = '2fa_challenge_' . $challengeToken;
        $item = $this->cache->getItem($cacheKey);
        $userId = $item->isHit() ? $item->get() : null;

        if (!$userId) {
            return $this->json(['message' => 'Session de connexion expirée, reconnectez-vous.'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->userRepository->find($userId);
        if (!$user || !$user->isTwoFactorEnabled() || !$user->getTotpSecret()) {
            return $this->json(['message' => 'Session de connexion invalide.'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->twoFactorAuthenticator->verify($user->getTotpSecret(), $code)) {
            return $this->json(['message' => 'Code invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // One-time use: a code that already issued a token can't be replayed for a second one.
        $this->cache->deleteItem($cacheKey);

        return $this->json(['token' => $this->jwtManager->create($user)]);
    }
}
