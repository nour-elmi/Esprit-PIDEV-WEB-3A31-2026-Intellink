<?php

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function findNewest(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')->addSelect('a')
            ->leftJoin('p.images', 'i')->addSelect('i')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAdminFeed(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')->addSelect('a')
            ->leftJoin('p.images', 'i')->addSelect('i')
            ->orderBy('p.isPinned', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findNewestActiveOnly(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')->addSelect('a')
            ->leftJoin('p.images', 'i')->addSelect('i')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'ACTIVE')
            ->orderBy('p.isPinned', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function searchActivePosts(string $search): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')->addSelect('a')
            ->leftJoin('p.images', 'i')->addSelect('i')
            ->andWhere('p.status = :status')
            ->andWhere('LOWER(p.content) LIKE :search')
            ->setParameter('status', 'ACTIVE')
            ->setParameter('search', '%' . mb_strtolower(trim($search)) . '%')
            ->orderBy('p.isPinned', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function searchAdminPosts(string $search, string $statusFilter = 'Tous'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')->addSelect('a')
            ->leftJoin('p.images', 'i')->addSelect('i')
            ->andWhere('LOWER(p.content) LIKE :search')
            ->setParameter('search', '%' . mb_strtolower(trim($search)) . '%');

        if ($statusFilter === 'Actifs') {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', 'ACTIVE');
        }

        if ($statusFilter === 'Masqués' || $statusFilter === 'Masques') {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', 'HIDDEN');
        }

        return $qb
            ->orderBy('p.isPinned', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function updateContent(int $postId, int $userId, string $content): bool
    {
        return $this->createQueryBuilder('p')
            ->update()
            ->set('p.content', ':content')
            ->set('p.isEdited', ':edited')
            ->where('p.id = :postId')
            ->andWhere('p.author = :userId')
            ->setParameter('content', $content)
            ->setParameter('edited', true)
            ->setParameter('postId', $postId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute() > 0;
    }
}