<?php

namespace App\Repository;

use App\Entity\SaleItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SaleItem>
 */
class SaleItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaleItem::class);
    }

    public function salesByProduct($startDate, $endDate, ?int $doctorId = null)
    {
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder("i")
            ->select("i.pName as name, SUM(i.quantity * i.price) as revenue")
            ->join("i.sale", "s")
            ->where("s.created_at >= :start")
            ->andWhere("s.created_at <= :end")
            ->setParameter("start", $start)
            ->setParameter("end", $end);

        if ($doctorId) {
            $qb->andWhere("s.doctor = :doctorId")
               ->setParameter("doctorId", $doctorId);
        }

        return $qb->groupBy("i.pName")
            ->orderBy("revenue", "DESC")
            ->getQuery()
            ->getResult();
    }
}
