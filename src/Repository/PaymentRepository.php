<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * @return float
     */
    public function sumBySale(int $saleId): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.sale = :saleId')
            ->setParameter('saleId', $saleId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return float
     */
    public function sumByPurchase(int $purchaseId): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.purchase = :purchaseId')
            ->setParameter('purchaseId', $purchaseId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumByDate(\DateTimeInterface $start, \DateTimeInterface $end, string $type = 'sale'): float
    {
        $qb = $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($type === 'sale') {
            $qb->andWhere('p.sale IS NOT NULL');
        } else {
            $qb->andWhere('p.purchase IS NOT NULL');
        }

        return (float) $qb->getQuery()->getSingleScalarResult();
    }
}
