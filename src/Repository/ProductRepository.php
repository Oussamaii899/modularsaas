<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findLowStock(int $threshold = 10)
    {
        return $this->createQueryBuilder('p')
            ->where('p.stockQuantity <= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('p.stockQuantity', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Returns full Product entities with stock at or below the threshold.
     */
    public function findLowStockProducts(int $threshold = 5): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.stockQuantity <= :threshold')
            ->andWhere('p.stockQuantity >= 0')
            ->setParameter('threshold', $threshold)
            ->orderBy('p.stockQuantity', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchAndPaginate(?string $query, int $page = 1, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($query) {
            $qb->andWhere('p.name LIKE :query OR p.description LIKE :query OR p.sku LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        $totalItems = count($qb->getQuery()->getResult());
        $pagesCount = (int) ceil($totalItems / $limit);
        $page = max(1, min($page, max($pagesCount, 1)));

        $items = $qb->orderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
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
