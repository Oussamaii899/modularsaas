<?php

namespace App\Repository;

use App\Entity\Contact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }
    public function searchAndPaginate(string $type, ?string $query = null, int $page = 1, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.type = :type')
            ->setParameter('type', $type);

        if ($query) {
            $qb->andWhere('c.name LIKE :query OR c.email LIKE :query OR c.phone LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        $queryResult = $qb->getQuery()->getResult();
        $totalItems = is_array($queryResult) ? count($queryResult) : 0;
        $pagesCount = (int) ceil($totalItems / $limit);

        $results = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('c.name', 'ASC')
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
