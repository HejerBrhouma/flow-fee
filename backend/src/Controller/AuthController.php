<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    private const MAX_AVATAR_SIZE = 5 * 1024 * 1024; // 5 MB
    private const ALLOWED_AVATAR_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer,
        private readonly UserRepository $userRepository,
        private readonly StorageInterface $storage,
        private readonly RequestStack $requestStack,
    ) {}

    /**
     * Resolves whichever avatar source applies (locally uploaded file takes priority over
     * an external OAuth provider URL) into an absolute URL on a transient property, since
     * the entity can't depend on the storage service itself.
     */
    private function withAvatarUrl(User $user): User
    {
        if ($user->getAvatarPath()) {
            $request = $this->requestStack->getCurrentRequest();
            $uri = $this->storage->resolveUri($user, 'avatarFile');
            $user->setAvatarUrl($uri !== null && $request ? $request->getSchemeAndHttpHost() . $uri : $uri);
        } else {
            $user->setAvatarUrl($user->getAvatar());
        }

        return $user;
    }

    private function serializeUser(User $user): array
    {
        return json_decode($this->serializer->serialize($this->withAvatarUrl($user), 'json', ['groups' => ['user:read']]), true);
    }

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
            'user' => $this->serializeUser($user),
        ], Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->serializeUser($user));
    }

    #[Route('/me', name: 'update_me', methods: ['PATCH'])]
    public function updateMe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('firstName', $data)) {
            $user->setFirstName($data['firstName'] ?? '');
        }
        if (array_key_exists('lastName', $data)) {
            $user->setLastName($data['lastName'] ?? '');
        }
        if (array_key_exists('phone', $data)) {
            $user->setPhone($data['phone'] ?: null);
        }
        if (array_key_exists('preferredCurrency', $data)) {
            $user->setPreferredCurrency($data['preferredCurrency'] ?: null);
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json($this->serializeUser($user));
    }

    #[Route('/me/avatar', name: 'update_avatar', methods: ['POST'])]
    public function updateAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $uploadedFile = $request->files->get('avatar');
        if (!$uploadedFile instanceof UploadedFile || !$uploadedFile->isValid()) {
            return $this->json(['message' => 'Fichier invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($uploadedFile->getMimeType(), self::ALLOWED_AVATAR_MIME_TYPES, true)) {
            return $this->json(['message' => 'Format non supporté (JPEG, PNG ou WEBP uniquement).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($uploadedFile->getSize() > self::MAX_AVATAR_SIZE) {
            return $this->json(['message' => 'Le fichier dépasse la taille maximale de 5 Mo.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setAvatarFile($uploadedFile);
        $user->touch();
        $this->em->flush();

        return $this->json($this->serializeUser($user));
    }

    #[Route('/me/avatar', name: 'delete_avatar', methods: ['DELETE'])]
    public function deleteAvatar(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $user->setAvatarPath(null);
        $user->touch();
        $this->em->flush();

        return $this->json($this->serializeUser($user));
    }

    #[Route('/me/password', name: 'update_password', methods: ['POST'])]
    public function updatePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';

        if (!$user->getPassword() || !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->json(['message' => 'Mot de passe actuel incorrect.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($newPassword) < 8) {
            return $this->json(['message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->em->flush();

        return $this->json(['message' => 'Mot de passe mis à jour.']);
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

    #[Route('/oauth/mobile/google', name: 'oauth_mobile_google', methods: ['GET'])]
    public function connectGoogleMobile(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('google_mobile')->redirect(['email', 'profile']);
    }

    #[Route('/oauth/mobile/google/check', name: 'oauth_check_google_mobile', methods: ['GET'])]
    public function connectGoogleMobileCheck(): JsonResponse
    {
        // Handled by OAuthAuthenticator
        return $this->json(['message' => 'OK']);
    }

    #[Route('/oauth/mobile/facebook', name: 'oauth_mobile_facebook', methods: ['GET'])]
    public function connectFacebookMobile(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('facebook_mobile')->redirect(['email', 'public_profile']);
    }

    #[Route('/oauth/mobile/facebook/check', name: 'oauth_check_facebook_mobile', methods: ['GET'])]
    public function connectFacebookMobileCheck(): JsonResponse
    {
        // Handled by OAuthAuthenticator
        return $this->json(['message' => 'OK']);
    }
}
