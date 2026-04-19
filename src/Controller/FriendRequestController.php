<?php
namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\FriendRequest;
use App\Entity\Utilisateur; // 🔴 CORRIGÉ : On utilise bien TA classe Utilisateur
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/friend-request', name: 'api_friend_request_')]
class FriendRequestController extends AbstractController
{
    #[Route('/send/{receiverId}', name: 'send', methods: ['POST'])]
    public function sendRequest(int $receiverId, EntityManagerInterface $em): JsonResponse
    {
        $requester = $this->getUser(); 
        if (!$requester) return $this->json(['error' => 'Vous devez être connecté.'], 403);

        // 🔴 CORRIGÉ : On cherche dans le Repository Utilisateur
        $receiver = $em->getRepository(Utilisateur::class)->find($receiverId);
        
        if (!$receiver || $requester === $receiver) {
            return $this->json(['error' => 'Utilisateur invalide.'], 400);
        }

        // 🟢 SÉCURITÉ AJOUTÉE : Vérifier si la demande existe déjà !
        $existingRequest = $em->getRepository(FriendRequest::class)->findOneBy([
            'requester' => $requester,
            'receiver' => $receiver
        ]);
        
        if ($existingRequest) {
            return $this->json(['error' => 'Demande déjà envoyée à cet utilisateur !'], 400);
        }

        $friendRequest = new FriendRequest();
        $friendRequest->setRequester($requester);
        $friendRequest->setReceiver($receiver);
                $friendRequest->setStatus('PENDING');

        $em->persist($friendRequest);
        $em->flush();

        // ------------------
        // ENVOI PUSHER EVENT POUR NOTIFIER REVECEUR (TEMPS REEL)
        // ------------------
        try {
            $pusher = new \Pusher\Pusher(
                $_ENV['PUSHER_KEY'], 
                $_ENV['PUSHER_SECRET'], 
                $_ENV['PUSHER_APP_ID'],
                [
                    'cluster' => $_ENV['PUSHER_CLUSTER'], 
                    'useTLS' => true, 
                    'curl_options' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]
                ]
            );

            $requesterImage = $requester->getImage() ? (strpos($requester->getImage(), 'http') === 0 ? $requester->getImage() : '/uploads/profiles/' . $requester->getImage()) : 'https://ui-avatars.com/api/?name='.urlencode($requester->getNom()).'&background=fce7f3&color=db2777';

            $payloadToReceiver = [
                'requestId' => $friendRequest->getId(),
                'requesterId' => $requester->getId(),
                'requesterName' => $requester->getNom(),
                'requesterImage' => $requesterImage,
                'message' => $requester->getNom() . ' vous a envoyé une demande d\'ami.'
            ];
            $pusher->trigger('user-channel-' . $receiver->getId(), 'friend-request-received', $payloadToReceiver);
        } catch (\Throwable $e) {}

        return $this->json(['message' => 'Demande envoyée !'], 201);
    }

    #[Route('/accept/{requestId}', name: 'accept', methods: ['POST'])]
    public function acceptRequest(int $requestId, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $friendRequest = $em->getRepository(FriendRequest::class)->find($requestId);

        if (!$friendRequest || $friendRequest->getReceiver() !== $user) {
            return $this->json(['error' => 'Non autorisé.'], 403);
        }

        $friendRequest->setStatus('ACCEPTED');

        // Création de la conversation
        $conversation = new Conversation();
        $conversation->addParticipant($friendRequest->getRequester());
        $conversation->addParticipant($friendRequest->getReceiver());

                $em->persist($friendRequest);
        $em->persist($conversation);
        $em->flush();

        // ------------------
        // ENVOI PUSHER EVENT
        // ------------------
        $pusher = new \Pusher\Pusher(
            $_ENV['PUSHER_KEY'], 
            $_ENV['PUSHER_SECRET'], 
            $_ENV['PUSHER_APP_ID'],
            [
                'cluster' => $_ENV['PUSHER_CLUSTER'], 
                'useTLS' => true, 
                'curl_options' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]
            ]
        );

        $r = $friendRequest->getRequester();
        $u = $user;

        $rImage = $r->getImage() ? (strpos($r->getImage(), 'http') === 0 ? $r->getImage() : '/uploads/profiles/' . $r->getImage()) : 'https://ui-avatars.com/api/?name='.urlencode($r->getNom()).'&background=f1f5f9&color=000';
        $uImage = $u->getImage() ? (strpos($u->getImage(), 'http') === 0 ? $u->getImage() : '/uploads/profiles/' . $u->getImage()) : 'https://ui-avatars.com/api/?name='.urlencode($u->getNom()).'&background=f1f5f9&color=000';

        $payloadToRequester = [
            'type' => 'friend-accepted',
            'friendId' => $u->getId(),
            'friendName' => $u->getNom(),
            'friendImage' => $uImage,
            'conversationId' => $conversation->getId(),
            'requestId' => $friendRequest->getId()
        ];
        $pusher->trigger('user-channel-' . $r->getId(), 'friend-accepted-event', $payloadToRequester);

        $payloadToReceiver = [
            'type' => 'friend-accepted',
            'friendId' => $r->getId(),
            'friendName' => $r->getNom(),
            'friendImage' => $rImage,
            'conversationId' => $conversation->getId(),
            'requestId' => $friendRequest->getId()
        ];
        $pusher->trigger('user-channel-' . $u->getId(), 'friend-accepted-event', $payloadToReceiver);


        return $this->json(['message' => 'Acceptée ! Conversation créée.'], 200);
    }
}