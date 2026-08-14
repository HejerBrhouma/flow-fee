<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Single entry point for notifying a user — persists the in-app Notification row (shown in
 * the notification bell / notifications page) and fires the push notification alongside it,
 * so every call site gets both channels for free instead of remembering to wire push in
 * separately. Replaces the inline `new Notification()` construction that used to be
 * duplicated across ExpenseController and SavingsGoalController.
 */
class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    /**
     * Persists the notification but does not flush — callers that notify several users in a
     * loop (e.g. all company admins on a budget alert) flush once at the end, matching the
     * pattern already used at every existing call site.
     *
     * @param array<string, mixed> $data
     */
    public function notify(User $user, string $type, string $message, array $data = []): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setMessage($message);
        $notification->setData($data ?: null);

        $this->em->persist($notification);

        $this->pushNotificationService->sendToUser($user, 'Flow Fee', $message, $data);
    }
}
