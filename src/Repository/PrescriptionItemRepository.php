<?php

namespace App\Repository;

use App\Entity\PrescriptionItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrescriptionItem>
 *
 * @method PrescriptionItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method PrescriptionItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method PrescriptionItem[]    findAll()
 * @method PrescriptionItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PrescriptionItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrescriptionItem::class);
    }
}
