<?php

namespace App\Repository;

use App\Entity\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sale>
 */
class SaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sale::class);
    }


    public function totalByDate($startDate, $endDate){
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        return $this->createQueryBuilder("s")
        ->select("SUM(s.total) as total")
        ->where("s.created_at >= :start")
        ->andWhere("s.created_at <= :end")
        ->andWhere("s.paymentStatus != 'Cancelled'")
        ->setParameter("start", $start)
        ->setParameter("end", $end)
        ->getQuery()
        ->getSingleScalarResult();
    }


    public function totalCollectedByDate($startDate, $endDate)
    {
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        return $this->createQueryBuilder("s")
            ->select("SUM(p.amount)")
            ->join("s.payments", "p")
            ->where("p.createdAt >= :start")
            ->andWhere("p.createdAt <= :end")
            ->andWhere("s.paymentStatus != 'Cancelled'")
            ->andWhere("p.type != 'Refund'")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function totalRefundedByDate($startDate, $endDate)
    {
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        return $this->createQueryBuilder("s")
            ->select("SUM(p.amount)")
            ->join("s.payments", "p")
            ->where("p.createdAt >= :start")
            ->andWhere("p.createdAt <= :end")
            ->andWhere("s.paymentStatus != 'Cancelled'")
            ->andWhere("p.type = 'Refund'")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function totalNetCollectedByDate($startDate, $endDate)
    {
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        return $this->createQueryBuilder("s")
            ->select("SUM(p.amount)")
            ->join("s.payments", "p")
            ->where("p.createdAt >= :start")
            ->andWhere("p.createdAt <= :end")
            ->andWhere("s.paymentStatus != 'Cancelled'")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Outstanding = invoiced total - net paid (includes refunds) for non-cancelled sales.
     * Refunds must be included here so overpayment refunds don't create a phantom negative balance.
     */
    public function totalOutstandingByDate($startDate, $endDate): float
    {
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        $invoiced = (float) ($this->createQueryBuilder("s")
            ->select("SUM(s.total)")
            ->where("s.created_at >= :start")
            ->andWhere("s.created_at <= :end")
            ->andWhere("s.paymentStatus != 'Cancelled'")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        $netPaid = (float) ($this->createQueryBuilder("s")
            ->select("SUM(p.amount)")
            ->join("s.payments", "p")
            ->where("s.created_at >= :start")
            ->andWhere("s.created_at <= :end")
            ->andWhere("s.paymentStatus != 'Cancelled'")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        return max(0.0, $invoiced - $netPaid);
    }

    public function salesByDate($startDate, $endDate){
        $start = new \DateTimeImmutable($startDate);
        $end = (new \DateTimeImmutable($endDate))->setTime(23, 59, 59);

        return $this->createQueryBuilder("s")
            ->select("SUBSTRING(p.createdAt, 1, 10) as date, SUM(p.amount) as total")
            ->join("s.payments", "p")
            ->where("p.createdAt >= :start")
            ->andWhere("p.createdAt <= :end")
            ->andWhere("s.paymentStatus != 'Cancelled'")
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

        return $this->createQueryBuilder('s')
            ->addSelect('c')
            ->leftJoin('s.contact', 'c')
            ->where('s.created_at >= :start')
            ->andWhere('s.created_at <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('s.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
    public function searchAndPaginate( ?string $query = null, int $page = 1, int $limit = 10, ?string $status = null, ?int $contactId = null, string $sortBy = 'created_at', string $sortDir = 'DESC' ): array {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.contact', 'c')
            ->addSelect('c');

        if ($query) {
            $qb->andWhere('c.name LIKE :query OR s.id LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        if ($status) {
            $qb->andWhere('s.paymentStatus = :status')
               ->setParameter('status', $status);
        }

        if ($contactId) {
            $qb->andWhere('c.id = :contactId')
               ->setParameter('contactId', $contactId);
        }

        $allowedSortFields = ['created_at', 'total', 'id'];
        $sortField = in_array($sortBy, $allowedSortFields) ? 's.' . $sortBy : 's.created_at';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($sortField, $sortDir);

        $countQb = clone $qb;
        $totalItems = count($countQb->getQuery()->getResult());
        $pagesCount = (int) ceil($totalItems / $limit);

        $results = $qb->setFirstResult(($page - 1) * $limit)
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
}
