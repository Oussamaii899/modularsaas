<?php

namespace App\Repository;

use App\Entity\PatientDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PatientDocument>
 *
 * @method PatientDocument|null find($id, $lockMode = null, $lockVersion = null)
 * @method PatientDocument|null findOneBy(array $criteria, array $orderBy = null)
 * @method PatientDocument[]    findAll()
 * @method PatientDocument[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PatientDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatientDocument::class);
    }
}
