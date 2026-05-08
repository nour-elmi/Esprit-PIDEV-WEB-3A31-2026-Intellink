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
            ->select('f.idFormation AS idFormation')
            ->addSelect('(SELECT COUNT(p2.idParticipation) FROM App\Entity\formation\Participation p2 WHERE p2.formation = f) AS HIDDEN popularity')
            ->andWhere('f.domaine IN (:domaines)')
            ->setParameter('domaines', $domaines)
            ->orderBy('popularity', 'DESC')
            ->addOrderBy('f.idFormation', 'DESC')
            ->setMaxResults(max(1, $limit));

        if ($excludeIds !== []) {
            $qb
                ->andWhere('f.idFormation NOT IN (:excludeIds)')
                ->setParameter('excludeIds', array_values(array_unique(array_map('intval', $excludeIds))));
        }

        $rows = $qb->getQuery()->getArrayResult();
        $ids = array_map(
            static fn(array $row): int => (int) ($row['idFormation'] ?? 0),
            $rows
        );

        return $this->fetchFormationsByOrderedIds($ids);
    }

    /**
     * @param int[] $excludeIds
     *
     * @return Formation[]
     */
    public function findTopFormationsExcludingIds(array $excludeIds, int $limit = 4): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('f.idFormation AS idFormation')
            ->addSelect('(SELECT COUNT(p2.idParticipation) FROM App\Entity\formation\Participation p2 WHERE p2.formation = f) AS HIDDEN popularity')
            ->orderBy('popularity', 'DESC')
            ->addOrderBy('f.idFormation', 'DESC')
            ->setMaxResults(max(1, $limit));

        if ($excludeIds !== []) {
            $qb
                ->andWhere('f.idFormation NOT IN (:excludeIds)')
                ->setParameter('excludeIds', array_values(array_unique(array_map('intval', $excludeIds))));
        }

        $rows = $qb->getQuery()->getArrayResult();
        $ids = array_map(
            static fn(array $row): int => (int) ($row['idFormation'] ?? 0),
            $rows
        );

        return $this->fetchFormationsByOrderedIds($ids);
    }

    /**
     * @param int[] $ids
     * @return Formation[]
     */
    private function fetchFormationsByOrderedIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $formations = $this->createQueryBuilder('f')
            ->andWhere('f.idFormation IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $positionById = array_flip($ids);
        usort(
            $formations,
            static fn(Formation $a, Formation $b): int =>
                ($positionById[$a->getIdFormation() ?? 0] ?? PHP_INT_MAX)
                <=>
                ($positionById[$b->getIdFormation() ?? 0] ?? PHP_INT_MAX)
        );

        return $formations;
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
