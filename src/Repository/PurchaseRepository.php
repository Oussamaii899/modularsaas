<?php

namespace App\Repository;

use App\Entity\Purchase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Purchase>
 */
class PurchaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Purchase::class);
    }
    public function totalByDate($startDate, $endDate){
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        return $this->createQueryBuilder("p")
        ->select("SUM(p.total) as total")
        ->where("p.created_at >= :start")
        ->andWhere("p.created_at <= :end")
        ->andWhere("p.paymentStatus != 'Cancelled'")
        ->setParameter("start", $start)
        ->setParameter("end", $end)
        ->getQuery()
        ->getSingleScalarResult();
    }

    public function totalPaidByDate($startDate, $endDate)
    {
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        return $this->createQueryBuilder("p")
            ->select("SUM(pmt.amount)")
            ->join("p.payments", "pmt")
            ->where("pmt.createdAt >= :start")
            ->andWhere("pmt.createdAt <= :end")
            ->andWhere("p.paymentStatus != 'Cancelled'")
            ->andWhere("pmt.type != 'Refund'")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function totalRefundedByDate($startDate, $endDate)
    {
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        return $this->createQueryBuilder("p")
            ->select("SUM(pmt.amount)")
            ->join("p.payments", "pmt")
            ->where("pmt.createdAt >= :start")
            ->andWhere("pmt.createdAt <= :end")
            ->andWhere("p.paymentStatus != 'Cancelled'")
            ->andWhere("pmt.type = 'Refund'")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function totalNetPaidByDate($startDate, $endDate)
    {
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        return $this->createQueryBuilder("p")
            ->select("SUM(pmt.amount)")
            ->join("p.payments", "pmt")
            ->where("pmt.createdAt >= :start")
            ->andWhere("pmt.createdAt <= :end")
            ->andWhere("p.paymentStatus != 'Cancelled'")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function totalOutstandingByDate($startDate, $endDate): float
    {
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        $invoiced = (float) ($this->createQueryBuilder("p")
            ->select("SUM(p.total)")
            ->where("p.created_at >= :start")
            ->andWhere("p.created_at <= :end")
            ->andWhere("p.paymentStatus != 'Cancelled'")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        $netPaid = (float) ($this->createQueryBuilder("p")
            ->select("SUM(pmt.amount)")
            ->join("p.payments", "pmt")
            ->where("p.created_at >= :start")
            ->andWhere("p.created_at <= :end")
            ->andWhere("p.paymentStatus != 'Cancelled'")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        return max(0.0, $invoiced - $netPaid);
    }

    public function purchasesByDate($startDate, $endDate){
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        return $this->createQueryBuilder("p")
            ->select("SUBSTRING(pay.createdAt, 1, 10) as date, SUM(pay.amount) as total")
            ->join("p.payments", "pay")
            ->where("pay.createdAt >= :start")
            ->andWhere("pay.createdAt <= :end")
            ->andWhere("p.paymentStatus != 'Cancelled'")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->groupBy("date")
            ->getQuery()
            ->getResult();
    }

    public function ordersCountByDate($startDate, $endDate){
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        return $this->createQueryBuilder("p")
            ->select("SUBSTRING(p.createdAt, 1, 10) as date, COUNT(p.id) as total")
            ->where("p.createdAt >= :start")
            ->andWhere("p.createdAt <= :end")
            ->andWhere("p.paymentStatus != 'Cancelled'")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->groupBy("date")
            ->getQuery()
            ->getResult();
    }

    public function findRecent($startDate, $endDate, int $limit = 5)
    {
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->addSelect('c')
            ->leftJoin('p.contact', 'c')
            ->where('p.created_at >= :start')
            ->andWhere('p.created_at <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
    public function searchAndPaginate( ?string $query = null, int $page = 1, int $limit = 10, ?string $status = null, ?int $contactId = null, string $sortBy = 'created_at', string $sortDir = 'DESC' ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.contact', 'c')
            ->addSelect('c');

        if ($query) {
            $trimmedQuery = trim($query);
            $qb->leftJoin('p.purchaseItems', 'pi')
               ->leftJoin('pi.product', 'prod');

            $cleanId = preg_replace('/[^0-9]/', '', $trimmedQuery);

            if ($cleanId !== '') {
                $qb->andWhere('c.name LIKE :query OR p.id = :idVal OR pi.pName LIKE :query OR prod.name LIKE :query')
                   ->setParameter('query', '%' . $trimmedQuery . '%')
                   ->setParameter('idVal', (int)$cleanId);
            } else {
                $qb->andWhere('c.name LIKE :query OR pi.pName LIKE :query OR prod.name LIKE :query')
                   ->setParameter('query', '%' . $trimmedQuery . '%');
            }
        }

        if ($status) {
            $qb->andWhere('p.paymentStatus = :status')
               ->setParameter('status', $status);
        }

        if ($contactId) {
            $qb->andWhere('c.id = :contactId')
               ->setParameter('contactId', $contactId);
        }

        $allowedSortFields = ['created_at', 'total', 'id'];
        $sortField = in_array($sortBy, $allowedSortFields) ? 'p.' . $sortBy : 'p.created_at';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($sortField, $sortDir);

        $countQb = clone $qb;
        $totalItems = (int) $countQb->select('COUNT(DISTINCT p.id)')->getQuery()->getSingleScalarResult();
        $pagesCount = (int) ceil($totalItems / $limit);

        $results = $qb->distinct()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $results,
            'pagesCount' => $pagesCount,
            'currentPage' => $page,
            'totalItems' => $totalItems
        ];
    }

    /**
     * Find all unpaid or partially paid purchases (for outstanding payable notifications).
     */
    public function findUnpaidOrPartial(): array
    {
        return $this->createQueryBuilder('p')
            ->where("p.paymentStatus IN ('Unpaid', 'Partial')")
            ->orderBy('p.created_at', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
