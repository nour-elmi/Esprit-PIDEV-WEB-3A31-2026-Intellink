<?php

namespace App\Repository;

use App\Entity\Emploi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Emploi>
 */
class EmploiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Emploi::class);
    }


    /** @return list<Emploi> */
    public function searchByTerm(?string $term): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($term) {
            $qb->andWhere('e.titre LIKE :term OR e.nom_entreprise LIKE :term')
            ->setParameter('term', '%' . $term . '%');
        }

        return $qb->orderBy('e.date_debut', 'DESC')
                ->getQuery()
                ->getResult();
    }

    /** @return list<Emploi> */
    public function sortByField(string $field, string $order = 'ASC'): array
    {
        $allowedFields = ['salaire', 'date_expiration', 'date_debut'];
        $field = in_array($field, $allowedFields) ? $field : 'date_debut';
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        return $this->createQueryBuilder('e')
            ->orderBy('e.' . $field, $order)
            ->getQuery()
            ->getResult();
    }
    



//    /**
//     * @return Emploi[] Returns an array of Emploi objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Emploi
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
