<?php

namespace App\Twig;

use App\Entity\FriendRequest;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_pending_friend_requests', [$this, 'getPendingFriendRequests']),
            new TwigFunction('are_friends', [$this, 'areFriends']),
        ];
    }

    public function areFriends(Utilisateur $user1, Utilisateur $user2): bool
    {
        if ($user1 === $user2) {
            return true;
        }

        $repo = $this->em->getRepository(FriendRequest::class);
        
        $request1 = $repo->findOneBy([
            'requester' => $user1,
            'receiver' => $user2,
            'status' => 'ACCEPTED'
        ]);

        if ($request1) {
            return true;
        }

        $request2 = $repo->findOneBy([
            'requester' => $user2,
            'receiver' => $user1,
            'status' => 'ACCEPTED'
        ]);

        return $request2 !== null;
    }

    public function getPendingFriendRequests(Utilisateur $user): array
    {
        return $this->em->getRepository(FriendRequest::class)->findBy([
            'receiver' => $user,
            'status' => 'PENDING'
        ]);
    }
}
