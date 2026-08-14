<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\DeviceTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Psr\Log\LoggerInterface;

/**
 * Sends push notifications via Firebase Cloud Messaging, alongside the in-app Notification
 * row (see NotificationService — that's the single call site both go through). Requires a
 * Firebase service account JSON, configured via the FIREBASE_CREDENTIALS env var (a file
 * path). Fails open when that isn't configured: a missing/misconfigured Firebase project
 * shouldn't ever block the in-app notification or the action that triggered it (approving an
 * expense, crossing a budget threshold, etc.) from working.
 */
class PushNotificationService
{
    private ?Messaging $messaging = null;
    private bool $initialized = false;

    public function __construct(
        private readonly DeviceTokenRepository $deviceTokenRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly ?string $credentialsPath,
    ) {}

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = array_map(
            fn ($dt) => $dt->getToken(),
            $this->deviceTokenRepository->findBy(['user' => $user])
        );

        if (empty($tokens)) {
            return;
        }

        $messaging = $this->getMessaging();
        if (!$messaging) {
            return;
        }

        // FCM data payload values must all be strings.
        $stringData = array_map(static fn ($v) => (string) $v, $data);

        $message = CloudMessage::new()
            ->withNotification(FirebaseNotification::create($title, $body))
            ->withData($stringData);

        try {
            $report = $messaging->sendMulticast($message, $tokens);

            // Prune tokens FCM reports as invalid (app uninstalled, token rotated) so future
            // sends don't keep retrying a dead device.
            foreach ($report->invalidTokens() as $invalidToken) {
                $deviceToken = $this->deviceTokenRepository->findOneBy(['token' => $invalidToken]);
                if ($deviceToken) {
                    $this->em->remove($deviceToken);
                }
            }
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->warning('Push notification send failed.', ['exception' => $e->getMessage()]);
        }
    }

    private function getMessaging(): ?Messaging
    {
        if ($this->initialized) {
            return $this->messaging;
        }
        $this->initialized = true;

        if (!$this->credentialsPath || !is_readable($this->credentialsPath)) {
            $this->logger->info('Firebase credentials not configured — push notifications disabled.');
            return null;
        }

        try {
            $factory = (new Factory())->withServiceAccount($this->credentialsPath);
            $this->messaging = $factory->createMessaging();
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to initialize Firebase messaging.', ['exception' => $e->getMessage()]);
        }

        return $this->messaging;
    }
}
