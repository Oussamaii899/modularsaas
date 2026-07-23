<?php

namespace App\Repository;

use App\Entity\ProductItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductItem>
 */
class ProductItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductItem::class);
    }

    /**
     * @return ProductItem[]
     */
    public function findAvailableItemsByProduct(int $productId, int $limit): array
    {
        return $this->createQueryBuilder('pi')
            ->andWhere('pi.product = :productId')
            ->andWhere('pi.status IN (:statuses)')
            ->setParameter('productId', $productId)
            ->setParameter('statuses', [ProductItem::STATUS_AVAILABLE, ProductItem::STATUS_REFUNDED_OK])
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
