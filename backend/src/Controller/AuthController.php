<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer,
        private readonly UserRepository $userRepository,
    ) {}

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->findOneBy(['email' => $data['email'] ?? ''])) {
            return $this->json(['message' => 'Cette adresse email est déjà utilisée.'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($data['email'] ?? '');
        $user->setFirstName($data['firstName'] ?? '');
        $user->setLastName($data['lastName'] ?? '');
        $user->setType($data['type'] ?? User::TYPE_PERSONAL);

        if (!empty($data['password'])) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($user);
        $this->em->flush();

        $token = $this->jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'user' => json_decode($this->serializer->serialize($user, 'json', ['groups' => ['user:read']])),
        ], Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(
            json_decode($this->serializer->serialize($user, 'json', ['groups' => ['user:read']]))
        );
    }

    #[Route('/oauth/google', name: 'oauth_google', methods: ['GET'])]
    public function connectGoogle(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('google')->redirect(['email', 'profile']);
    }

    #[Route('/oauth/google/check', name: 'oauth_check_google', methods: ['GET'])]
    public function connectGoogleCheck(ClientRegistry $clientRegistry): JsonResponse
    {
        // Handled by OAuthAuthenticator
        return $this->json(['message' => 'OK']);
    }

    #[Route('/oauth/facebook', name: 'oauth_facebook', methods: ['GET'])]
    public function connectFacebook(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('facebook')->redirect(['email', 'public_profile']);
    }

    #[Route('/oauth/facebook/check', name: 'oauth_check_facebook', methods: ['GET'])]
    public function connectFacebookCheck(ClientRegistry $clientRegistry): JsonResponse
    {
        // Handled by OAuthAuthenticator
        return $this->json(['message' => 'OK']);
    }
}
