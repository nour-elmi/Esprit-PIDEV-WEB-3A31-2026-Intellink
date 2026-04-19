<?php

namespace App\Repository;

use App\Entity\Report;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Report>
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    public function hasOpenReport(string $type, int $targetId, int $userId): bool
    {
        $result = $this->createQueryBuilder('r')
            ->select('1')
            ->andWhere('r.type = :type')
            ->andWhere('r.targetId = :targetId')
            ->andWhere('r.reporterId = :userId')
            ->andWhere('r.status = :status')
            ->setParameter('type', $type)
            ->setParameter('targetId', $targetId)
            ->setParameter('userId', $userId)
            ->setParameter('status', 'OPEN')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }

    public function findOpenReports(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', 'OPEN')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}