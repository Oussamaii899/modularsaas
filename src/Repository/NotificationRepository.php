<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Get recent unread notifications for a specific user (up to $limit)
     */
    public function findUnreadForUser(User $user, int $limit = 15): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all recent notifications for a user (read + unread)
     */
    public function findRecentForUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count unread notifications for a user
     */
    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Check if a specific notification type/key already exists (to avoid duplicates)
     * Uses the title as a dedup key scoped to the user and created within $withinHours
     */
    public function existsRecentForUser(User $user, string $title, int $withinHours = 24): bool
    {
        $since = (new \DateTime())->modify("-{$withinHours} hours");

        $count = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.title = :title')
            ->andWhere('n.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('title', $title)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Mark all unread notifications as read for a user
     */
    public function markAllReadForUser(User $user): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
