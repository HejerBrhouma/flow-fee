<?php

namespace App\Controller;

use App\Entity\DeviceToken;
use App\Entity\User;
use App\Repository\DeviceTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/push', name: 'api_push_')]
class PushTokenController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeviceTokenRepository $deviceTokenRepository,
    ) {}

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        $token = $data['token'] ?? null;
        $platform = $data['platform'] ?? DeviceToken::PLATFORM_ANDROID;

        if (!$token) {
            return $this->json(['message' => 'Token requis.'], Response::HTTP_BAD_REQUEST);
        }

        // Upsert: the same physical device token might already be registered — to this user
        // (re-registering on app relaunch) or to a different one (device changed accounts
        // without logging out first, e.g. shared/demo devices) — either way it should end up
        // pointing at the current user.
        $deviceToken = $this->deviceTokenRepository->findOneBy(['token' => $token]) ?? new DeviceToken();
        $deviceToken->setToken($token);
        $deviceToken->setUser($user);
        $deviceToken->setPlatform($platform);

        $this->em->persist($deviceToken);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/unregister', name: 'unregister', methods: ['POST'])]
    public function unregister(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $token = $data['token'] ?? null;

        if ($token) {
            $deviceToken = $this->deviceTokenRepository->findOneBy(['token' => $token]);
            if ($deviceToken) {
                $this->em->remove($deviceToken);
                $this->em->flush();
            }
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
