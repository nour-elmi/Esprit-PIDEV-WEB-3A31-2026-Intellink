<?php

namespace App\Repository;

use App\Entity\formation\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    /**
     * @param string[] $domaines
     * @param int[] $excludeIds
     *
     * @return Formation[]
     */
    public function findTopFormationsByDomainExcludingIds(array $domaines, array $excludeIds, int $limit = 4): array
    {
        $domaines = array_values(array_unique(array_filter(array_map(
            static fn(mixed $domaine): string => trim((string) $domaine),
            $domaines
        ))));

        if ($domaines === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.participations', 'p')
            ->addSelect('COUNT(p.idParticipation) AS HIDDEN popularity')
            ->andWhere('f.domaine IN (:domaines)')
            ->setParameter('domaines', $domaines)
            ->groupBy('f.idFormation')
            ->orderBy('popularity', 'DESC')
            ->addOrderBy('f.idFormation', 'DESC')
            ->setMaxResults(max(1, $limit));

        if ($excludeIds !== []) {
            $qb
                ->andWhere('f.idFormation NOT IN (:excludeIds)')
                ->setParameter('excludeIds', array_values(array_unique(array_map('intval', $excludeIds))));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $excludeIds
     *
     * @return Formation[]
     */
    public function findTopFormationsExcludingIds(array $excludeIds, int $limit = 4): array
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.participations', 'p')
            ->addSelect('COUNT(p.idParticipation) AS HIDDEN popularity')
            ->groupBy('f.idFormation')
            ->orderBy('popularity', 'DESC')
            ->addOrderBy('f.idFormation', 'DESC')
            ->setMaxResults(max(1, $limit));

        if ($excludeIds !== []) {
            $qb
                ->andWhere('f.idFormation NOT IN (:excludeIds)')
                ->setParameter('excludeIds', array_values(array_unique(array_map('intval', $excludeIds))));
        }

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Formation[] Returns an array of Formation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Formation
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
