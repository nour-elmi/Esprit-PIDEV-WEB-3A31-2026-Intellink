<?php

namespace App\Repository;

use App\Entity\formation\FavoriFormation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FavoriFormation>
 */
class FavoriFormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoriFormation::class);
    }

    /**
     * @return int[]
     */
    public function findFormationIdsByUtilisateur(int $idUtilisateur): array
    {
        $rows = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.formation) AS formationId')
            ->andWhere('f.idUtilisateur = :idUtilisateur')
            ->setParameter('idUtilisateur', $idUtilisateur)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(
            static fn(array $row): int => (int) ($row['formationId'] ?? 0),
            $rows
        ));
    }
}

