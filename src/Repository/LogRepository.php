<?php

namespace App\Repository;

use App\Entity\Log;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Log>
 */
class LogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Log::class);
    }

    public function searchAndPaginateLogs(
        ?string $searchQuery = null,
        ?string $actionFilter = null,
        ?int $userIdFilter = null,
        ?string $sortField = 'createdAt',
        ?string $sortOrder = 'DESC',
        int $page = 1,
        int $limit = 10
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.user', 'u')
            ->addSelect('u');

        // Apply filters
        if ($searchQuery) {
            $qb->andWhere('l.details LIKE :query OR l.action LIKE :query OR u.username LIKE :query OR l.entityClass LIKE :query')
               ->setParameter('query', '%' . $searchQuery . '%');
        }

        if ($actionFilter) {
            $qb->andWhere('l.action = :action')
               ->setParameter('action', $actionFilter);
        }

        if ($userIdFilter) {
            $qb->andWhere('u.id = :userId')
               ->setParameter('userId', $userIdFilter);
        }

        // Apply sorting
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        $allowedSortFields = [
            'createdAt' => 'l.createdAt',
            'action' => 'l.action',
            'entity' => 'l.entityClass',
            'user' => 'u.username'
        ];
        
        $orderColumn = $allowedSortFields[$sortField] ?? 'l.createdAt';
        $qb->orderBy($orderColumn, $sortOrder);

        // Fetch counts and paginate
        $totalItems = count($qb->getQuery()->getResult());
        $pagesCount = (int) ceil($totalItems / $limit);
        $page = max(1, min($page, max($pagesCount, 1)));

        $items = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'pagesCount' => $pagesCount,
            'currentPage' => $page,
            'totalItems' => $totalItems
        ];
    }
}
