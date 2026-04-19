<?php

namespace App\Controller;

use App\Entity\Utilisateur; 
use App\Entity\FriendRequest; 
use App\Entity\Conversation; 
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Message;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class MessengerController extends AbstractController
{
    public function contactList(EntityManagerInterface $em): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser) return new Response('');

        // 1. Analyser toutes les demandes liées à l'utilisateur connecté
        $allRequests = $em->getRepository(FriendRequest::class)->createQueryBuilder('fr')
            ->where('fr.requester = :user OR fr.receiver = :user')
            ->setParameter('user', $currentUser)
            ->getQuery()
            ->getResult();

        $excludedIds = [$currentUser->getId()]; // On s'exclut soi-même d'office
        $pendingSentIds = []; // Les gens à qui j'ai envoyé une demande

        foreach ($allRequests as $req) {
            // Trouver qui est "l'autre" personne dans la demande
            $otherUser = ($req->getRequester() === $currentUser) ? $req->getReceiver() : $req->getRequester();

            if ($req->getStatus() === 'ACCEPTED') {
                // S'ils sont amis, on les exclut des suggestions !
                $excludedIds[] = $otherUser->getId();
            } elseif ($req->getStatus() === 'PENDING') {
                if ($req->getRequester() === $currentUser) {
                    // J'ai envoyé la demande : on garde dans les suggestions, mais on le note
                    $pendingSentIds[] = $otherUser->getId();
                } else {
                    // J'ai reçu la demande : on exclut des suggestions (car déjà affiché en haut)
                    $excludedIds[] = $otherUser->getId();
                }
            }
        }

        // 2. Récupérer les suggestions (en excluant les amis et ceux qui m'ont demandé)
        $qb = $em->getRepository(Utilisateur::class)->createQueryBuilder('u');
        $users = $qb->where('u.role = :role')
            ->andWhere($qb->expr()->notIn('u.id', $excludedIds))
            ->setParameter('role', 'ROLE_USER')
            ->getQuery()
            ->getResult();

        // 3. Demandes reçues (en haut du panel)
        $demandesRecues = $em->getRepository(FriendRequest::class)->findBy([
            'receiver' => $currentUser,
            'status' => 'PENDING'
        ]);

        // 4. Récupérer mes VRAIES conversations AVEC LE COMPTE DES NON LUS
        $conversations = $em->getRepository(Conversation::class)->createQueryBuilder('c')
            ->join('c.participants', 'p')->where('p = :user')->setParameter('user', $currentUser)->getQuery()->getResult();

        $conversationsData = [];
        $totalUnread = 0;

        foreach ($conversations as $conv) {
            // Compter les messages non lus de l'autre personne
            $qbMsg = $em->getRepository(Message::class)->createQueryBuilder('m');
            $unreadCount = $qbMsg->select('count(m.id)')
                ->where('m.conversation = :conv')->andWhere('m.isRead = false')->andWhere('m.expediteur != :user')
                ->setParameter('conv', $conv)->setParameter('user', $currentUser)->getQuery()->getSingleScalarResult();
            
            $totalUnread += $unreadCount;
            $conversationsData[] = ['entity' => $conv, 'unreadCount' => $unreadCount];
        }

        return $this->render('frontUser/_contact_list.html.twig', [
            'users' => $users, 'demandesRecues' => $demandesRecues,
            'conversationsData' => $conversationsData, 'totalUnread' => $totalUnread, // <-- Nouvelles variables
            'currentUser' => $currentUser, 'pendingSentIds' => $pendingSentIds
        ]);
    }
}