<?php

namespace App\Repository;

use App\Entity\Reaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reaction>
 */
class ReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reaction::class);
    }

    // ✅ get reactions by post
/** @return list<Reaction> */
public function findByPost(int $postId): array
{
    return $this->createQueryBuilder('r')
        ->andWhere('r.post = :postId')
        ->setParameter('postId', $postId)
        ->getQuery()
        ->getResult();
}

// ✅ get reaction of user on post
public function findByUserAndPost(int $userId, int $postId): ?Reaction
{
    return $this->createQueryBuilder('r')
        ->andWhere('r.author = :userId')
        ->andWhere('r.post = :postId')
        ->setParameter('userId', $userId)
        ->setParameter('postId', $postId)
        ->getQuery()
        ->getOneOrNullResult();
}

// ✅ count by type
public function countByType(int $postId, string $type): int
{
    return (int) $this->createQueryBuilder('r')
        ->select('COUNT(r.id)')
        ->andWhere('r.post = :postId')
        ->andWhere('r.type = :type')
        ->setParameter('postId', $postId)
        ->setParameter('type', $type)
        ->getQuery()
        ->getSingleScalarResult();
}

// ✅ score = UP - DOWN
public function getScore(int $postId): int
{
    return $this->countByType($postId, 'UP') - $this->countByType($postId, 'DOWN');
}
}
