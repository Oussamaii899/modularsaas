<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    #[Route('/api/notifications', name: 'api_notifications_list', methods: ['GET'])]
    public function list(
        NotificationService $notificationService,
        NotificationRepository $notificationRepository
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([], Response::HTTP_OK);
        }

        // Allow ROLE_ADMIN or users with see.notifications permission
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('see.notifications')) {
            return $this->json([], Response::HTTP_OK);
        }

        // Trigger dynamic checks (low stock, outstanding balances, AI cooldown)
        $notificationService->runChecksForUser($user);

        $notifications = $notificationRepository->findRecentForUser($user, 50);

        $data = [];
        foreach ($notifications as $notification) {
            $data[] = [
                'id'        => $notification->getId(),
                'title'     => $notification->getTitle(),
                'message'   => $notification->getMessage(),
                'type'      => $notification->getType(),
                'isRead'    => $notification->isRead(),
                'linkUrl'   => $notification->getLinkUrl(),
                'createdAt' => $notification->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/notifications/{id}/read', name: 'api_notifications_read', methods: ['POST'])]
    public function read(
        int $id,
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('see.notifications')) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $notification = $notificationRepository->find($id);

        if (!$notification) {
            return $this->json(['error' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        // Verify the notification belongs to the logged-in user
        if ($notification->getUser() !== $user) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $notification->setIsRead(true);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/api/notifications/read-all', name: 'api_notifications_read_all', methods: ['POST'])]
    public function readAll(
        NotificationRepository $notificationRepository
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('see.notifications')) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $notificationRepository->markAllReadForUser($user);

        return $this->json(['success' => true]);
    }
}
