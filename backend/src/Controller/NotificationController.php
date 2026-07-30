<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/notifications', name: 'api_notification_')]
class NotificationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notificationRepository,
        private readonly SerializerInterface $serializer,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $notifications = $this->notificationRepository->findBy(['user' => $user], ['createdAt' => 'DESC'], 50);

        return $this->json([
            'items' => json_decode($this->serializer->serialize($notifications, 'json', ['groups' => ['notification:read']])),
            'unreadCount' => $this->notificationRepository->count(['user' => $user, 'isRead' => false]),
        ]);
    }

    #[Route('/{id}/read', name: 'read', methods: ['POST'])]
    public function markRead(Notification $notification): JsonResponse
    {
        $this->assertOwner($notification);

        $notification->setIsRead(true);
        $this->em->flush();

        return $this->json(
            json_decode($this->serializer->serialize($notification, 'json', ['groups' => ['notification:read']]))
        );
    }

    #[Route('/read-all', name: 'read_all', methods: ['POST'])]
    public function markAllRead(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        foreach ($this->notificationRepository->findBy(['user' => $user, 'isRead' => false]) as $notification) {
            $notification->setIsRead(true);
        }
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Notification $notification): JsonResponse
    {
        $this->assertOwner($notification);

        $this->em->remove($notification);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function assertOwner(Notification $notification): void
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($notification->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
