<?php

namespace App\Repository;

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    public function findByPost(int $postId): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')->addSelect('a')
            ->leftJoin('c.parent', 'p')->addSelect('p')
            ->andWhere('c.post = :postId')
            ->setParameter('postId', $postId)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByPost(int $postId): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')->addSelect('a')
            ->leftJoin('c.parent', 'p')->addSelect('p')
            ->andWhere('c.post = :postId')
            ->andWhere('c.status = :status')
            ->setParameter('postId', $postId)
            ->setParameter('status', 'ACTIVE')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findThreadByPost(int $postId, string $sort = 'DESC', ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')->addSelect('a')
            ->leftJoin('c.parent', 'p')->addSelect('p')
            ->andWhere('c.post = :postId')
            ->andWhere('c.status = :status')
            ->setParameter('postId', $postId)
            ->setParameter('status', 'ACTIVE');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('LOWER(c.content) LIKE :search')
               ->setParameter('search', '%' . mb_strtolower(trim($search)) . '%');
        }

        $qb->orderBy('c.createdAt', strtoupper($sort) === 'ASC' ? 'ASC' : 'DESC');

        return $qb->getQuery()->getResult();
    }
    public function findAdminByPost(int $postId, ?string $search = null): array
{
    $qb = $this->createQueryBuilder('c')
        ->leftJoin('c.author', 'a')->addSelect('a')
        ->leftJoin('c.parent', 'p')->addSelect('p')
        ->andWhere('c.post = :postId')
        ->setParameter('postId', $postId);

    if ($search !== null && trim($search) !== '') {
        $qb->andWhere('LOWER(c.content) LIKE :search')
           ->setParameter('search', '%' . mb_strtolower(trim($search)) . '%');
    }

    $qb->orderBy('c.createdAt', 'DESC');

    return $qb->getQuery()->getResult();
}
}