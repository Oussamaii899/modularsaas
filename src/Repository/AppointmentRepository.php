<?php

namespace App\Repository;

use App\Entity\Appointment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 *
 * @method Appointment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Appointment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Appointment[]    findAll()
 * @method Appointment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    /**
     * Find appointments within a date range, optionally filtered by doctor.
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end, ?int $doctorId = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.startAt >= :start')
            ->andWhere('a.startAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.startAt', 'ASC');

        if ($doctorId) {
            $qb->andWhere('a.doctor = :doctorId')
               ->setParameter('doctorId', $doctorId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find upcoming appointments (within next N hours).
     */
    public function findUpcoming(int $hours = 1, ?int $doctorId = null): array
    {
        $now = new \DateTime();
        $until = (new \DateTime())->modify("+{$hours} hours");

        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.startAt >= :now')
            ->andWhere('a.startAt <= :until')
            ->andWhere('a.status = :status')
            ->setParameter('now', $now)
            ->setParameter('until', $until)
            ->setParameter('status', 'scheduled')
            ->orderBy('a.startAt', 'ASC');

        if ($doctorId) {
            $qb->andWhere('a.doctor = :doctorId')
               ->setParameter('doctorId', $doctorId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find today's appointments for a doctor.
     */
    public function findTodayForDoctor(int $doctorId): array
    {
        $todayStart = new \DateTime('today');
        $todayEnd = new \DateTime('tomorrow');

        return $this->createQueryBuilder('a')
            ->andWhere('a.startAt >= :start')
            ->andWhere('a.startAt < :end')
            ->andWhere('a.doctor = :doctorId')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->setParameter('doctorId', $doctorId)
            ->orderBy('a.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
