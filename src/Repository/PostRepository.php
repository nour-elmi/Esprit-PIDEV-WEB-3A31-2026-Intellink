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

    // ✅ afficherNewest()
    public function findNewest(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ✅ afficherAdminFeed()
    public function findAdminFeed(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.isPinned', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ✅ afficherNewestActiveOnly()
    public function findNewestActiveOnly(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'ACTIVE')
            ->orderBy('p.isPinned', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ✅ updateContent()
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