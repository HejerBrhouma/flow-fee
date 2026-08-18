<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\BudgetRepository;
use App\Repository\ExpenseRepository;
use App\Repository\NotificationRepository;
use App\Repository\SavingsGoalRepository;
use App\Repository\UserCompanyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
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
        private readonly BudgetRepository $budgetRepository,
        private readonly SavingsGoalRepository $savingsGoalRepository,
        private readonly ExpenseRepository $expenseRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly UserCompanyRepository $userCompanyRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $frontendUrl,
        private readonly string $mailerFrom,
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

    /**
     * RGPD data export: every piece of personal data this account owns, as a single JSON
     * document the user can download. Deliberately excludes other users' data even where
     * it's reachable (e.g. teammates in the same company) — only this account's own records.
     */
    #[Route('/me/export', name: 'export_data', methods: ['GET'])]
    public function exportData(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = [
            'exportedAt' => (new \DateTime())->format(DATE_ATOM),
            'profile' => $this->serializeUser($user),
            'expenses' => json_decode($this->serializer->serialize(
                $this->expenseRepository->findBy(['user' => $user]), 'json', ['groups' => ['expense:read']]
            ), true),
            'budgets' => json_decode($this->serializer->serialize(
                $this->budgetRepository->findBy(['user' => $user]), 'json', ['groups' => ['budget:read']]
            ), true),
            'savingsGoals' => json_decode($this->serializer->serialize(
                $this->savingsGoalRepository->findBy(['user' => $user]), 'json', ['groups' => ['savings_goal:read']]
            ), true),
            'notifications' => json_decode($this->serializer->serialize(
                $this->notificationRepository->findBy(['user' => $user]), 'json', ['groups' => ['notification:read']]
            ), true),
            'companyMemberships' => json_decode($this->serializer->serialize(
                $this->userCompanyRepository->findBy(['user' => $user]), 'json', ['groups' => ['team:read']]
            ), true),
        ];

        $response = $this->json($data);
        $response->headers->set('Content-Disposition', 'attachment; filename="flow-fee-donnees.json"');

        return $response;
    }

    /**
     * Account deletion. Most owned data cascades via orphanRemoval on the User entity
     * (expenses, company memberships, notifications) — but Budget and SavingsGoal only hold
     * a plain ManyToOne to User with no cascade configured, and Expense.reviewedBy is a
     * second, separate User reference (the approving manager) that must not take a
     * colleague's expense history down with it. Those three are cleaned up explicitly before
     * removing the user row so the deletion doesn't hit a stale FK constraint.
     */
    #[Route('/me', name: 'delete_account', methods: ['DELETE'])]
    public function deleteAccount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        if ($user->getPassword() && !$this->passwordHasher->isPasswordValid($user, $data['password'] ?? '')) {
            return $this->json(['message' => 'Mot de passe incorrect.'], Response::HTTP_UNAUTHORIZED);
        }

        foreach ($user->getUserCompanies() as $membership) {
            if ($membership->getRole() !== \App\Entity\UserCompany::ROLE_ADMIN) {
                continue;
            }
            $company = $membership->getCompany();
            $otherMembers = $this->userCompanyRepository->count(['company' => $company]) - 1;
            $otherAdmins = $this->userCompanyRepository->count(['company' => $company, 'role' => \App\Entity\UserCompany::ROLE_ADMIN]) - 1;
            if ($otherMembers > 0 && $otherAdmins === 0) {
                return $this->json([
                    'message' => "Vous êtes l'unique administrateur d'une entreprise comptant d'autres membres. Désignez un autre administrateur avant de supprimer votre compte.",
                ], Response::HTTP_CONFLICT);
            }
        }

        foreach ($this->budgetRepository->findBy(['user' => $user]) as $budget) {
            $this->em->remove($budget);
        }
        foreach ($this->savingsGoalRepository->findBy(['user' => $user]) as $goal) {
            $this->em->remove($goal);
        }
        foreach ($this->expenseRepository->findBy(['reviewedBy' => $user]) as $reviewedExpense) {
            $reviewedExpense->setReviewedBy(null);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Always returns the same generic message whether or not the email matches an account
     * (or an OAuth-only account with no local password) — this avoids leaking which emails
     * are registered. The reset link itself is only sent when there's actually a local
     * password to reset.
     */
    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $genericResponse = ['message' => 'Si un compte existe avec cette adresse, un email de réinitialisation vient d\'être envoyé.'];

        $user = $this->userRepository->findOneBy(['email' => $data['email'] ?? '']);
        if (!$user || !$user->getPassword()) {
            return $this->json($genericResponse);
        }

        $token = bin2hex(random_bytes(32));
        $user->setPasswordResetToken($token);
        $user->setPasswordResetTokenExpiresAt(new \DateTime('+1 hour'));
        $this->em->flush();

        $resetUrl = sprintf('%s/auth/reset-password?token=%s', rtrim($this->frontendUrl, '/'), $token);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe Flow Fee')
            ->text("Bonjour {$user->getFirstName()},\n\nVous avez demandé la réinitialisation de votre mot de passe Flow Fee. Cliquez sur le lien ci-dessous (valable 1 heure) :\n\n{$resetUrl}\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez cet email.")
            ->html("<p>Bonjour {$user->getFirstName()},</p><p>Vous avez demandé la réinitialisation de votre mot de passe Flow Fee. Cliquez sur le lien ci-dessous (valable 1 heure) :</p><p><a href=\"{$resetUrl}\">{$resetUrl}</a></p><p>Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.</p>");

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to send password reset email.', ['exception' => $e->getMessage()]);
        }

        return $this->json($genericResponse);
    }

    #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $token = $data['token'] ?? '';
        $newPassword = $data['newPassword'] ?? '';

        $user = $token ? $this->userRepository->findOneBy(['passwordResetToken' => $token]) : null;

        if (!$user || !$user->getPasswordResetTokenExpiresAt() || $user->getPasswordResetTokenExpiresAt() < new \DateTime()) {
            return $this->json(['message' => 'Lien de réinitialisation invalide ou expiré.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($newPassword) < 8) {
            return $this->json(['message' => 'Le mot de passe doit contenir au moins 8 caractères.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->setPasswordResetToken(null);
        $user->setPasswordResetTokenExpiresAt(null);
        $this->em->flush();

        return $this->json(['message' => 'Mot de passe réinitialisé avec succès.']);
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
