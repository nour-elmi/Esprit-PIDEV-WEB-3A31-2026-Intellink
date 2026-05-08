<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\FriendRequest;
use App\Entity\Utilisateur;
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
        // CHANGEMENT: typer explicitement l'utilisateur connecte pour PHPStan.
        // Ancien code (garde):
        // $requester = $this->getUser();
        // if (!$requester) return $this->json(['error' => 'Vous devez etre connecte.'], 403);
        $requester = $this->getUser();
        if (!$requester instanceof Utilisateur) {
            return $this->json(['error' => 'Vous devez etre connecte.'], 403);
        }

        $receiver = $em->getRepository(Utilisateur::class)->find($receiverId);
        if (!$receiver instanceof Utilisateur || $requester === $receiver) {
            return $this->json(['error' => 'Utilisateur invalide.'], 400);
        }

        $existingRequest = $em->getRepository(FriendRequest::class)->findOneBy([
            'requester' => $requester,
            'receiver' => $receiver,
        ]);
        if ($existingRequest) {
            return $this->json(['error' => 'Demande deja envoyee a cet utilisateur !'], 400);
        }

        $friendRequest = new FriendRequest();
        $friendRequest->setRequester($requester);
        $friendRequest->setReceiver($receiver);
        $friendRequest->setStatus('PENDING');

        $em->persist($friendRequest);
        $em->flush();

        try {
            $pusher = new \Pusher\Pusher(
                $_ENV['PUSHER_KEY'],
                $_ENV['PUSHER_SECRET'],
                $_ENV['PUSHER_APP_ID'],
                [
                    'cluster' => $_ENV['PUSHER_CLUSTER'],
                    'useTLS' => true,
                    'curl_options' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0],
                ]
            );

            $requesterImageValue = (string) ($requester->getImage() ?? '');
            $requesterName = (string) ($requester->getNom() ?? 'Utilisateur');
            $requesterImage = $requesterImageValue !== ''
                ? (strpos($requesterImageValue, 'http') === 0 ? $requesterImageValue : '/uploads/profiles/' . $requesterImageValue)
                : 'https://ui-avatars.com/api/?name=' . urlencode($requesterName) . '&background=fce7f3&color=db2777';

            $payloadToReceiver = [
                'requestId' => $friendRequest->getId(),
                'requesterId' => $requester->getId(),
                'requesterName' => $requesterName,
                'requesterImage' => $requesterImage,
                'message' => $requesterName . ' vous a envoye une demande d\'ami.',
            ];
            $pusher->trigger('user-channel-' . $receiver->getId(), 'friend-request-received', $payloadToReceiver);
        } catch (\Throwable) {
        }

        return $this->json(['message' => 'Demande envoyee !'], 201);
    }

    #[Route('/accept/{requestId}', name: 'accept', methods: ['POST'])]
    public function acceptRequest(int $requestId, EntityManagerInterface $em): JsonResponse
    {
        // CHANGEMENT: typer explicitement l'utilisateur connecte pour PHPStan.
        // Ancien code (garde): $user = $this->getUser();
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->json(['error' => 'Non autorise.'], 403);
        }

        $friendRequest = $em->getRepository(FriendRequest::class)->find($requestId);
        if (!$friendRequest || $friendRequest->getReceiver() !== $user) {
            return $this->json(['error' => 'Non autorise.'], 403);
        }

        $friendRequest->setStatus('ACCEPTED');

        $requesterEntity = $friendRequest->getRequester();
        $receiverEntity = $user;
        if (!$requesterEntity instanceof Utilisateur) {
            return $this->json(['error' => 'Demande invalide.'], 400);
        }

        $conversation = new Conversation();
        $conversation->addParticipant($requesterEntity);
        $conversation->addParticipant($receiverEntity);

        $em->persist($friendRequest);
        $em->persist($conversation);
        $em->flush();

        $pusher = new \Pusher\Pusher(
            $_ENV['PUSHER_KEY'],
            $_ENV['PUSHER_SECRET'],
            $_ENV['PUSHER_APP_ID'],
            [
                'cluster' => $_ENV['PUSHER_CLUSTER'],
                'useTLS' => true,
                'curl_options' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0],
            ]
        );

        $r = $requesterEntity;
        $u = $user;

        $rName = (string) ($r->getNom() ?? 'Utilisateur');
        $uName = (string) ($u->getNom() ?? 'Utilisateur');
        $rImageValue = (string) ($r->getImage() ?? '');
        $uImageValue = (string) ($u->getImage() ?? '');
        $rImage = $rImageValue !== ''
            ? (strpos($rImageValue, 'http') === 0 ? $rImageValue : '/uploads/profiles/' . $rImageValue)
            : 'https://ui-avatars.com/api/?name=' . urlencode($rName) . '&background=f1f5f9&color=000';
        $uImage = $uImageValue !== ''
            ? (strpos($uImageValue, 'http') === 0 ? $uImageValue : '/uploads/profiles/' . $uImageValue)
            : 'https://ui-avatars.com/api/?name=' . urlencode($uName) . '&background=f1f5f9&color=000';

        $payloadToRequester = [
            'type' => 'friend-accepted',
            'friendId' => $u->getId(),
            'friendName' => $uName,
            'friendImage' => $uImage,
            'conversationId' => $conversation->getId(),
            'requestId' => $friendRequest->getId(),
        ];
        $pusher->trigger('user-channel-' . $r->getId(), 'friend-accepted-event', $payloadToRequester);

        $payloadToReceiver = [
            'type' => 'friend-accepted',
            'friendId' => $r->getId(),
            'friendName' => $rName,
            'friendImage' => $rImage,
            'conversationId' => $conversation->getId(),
            'requestId' => $friendRequest->getId(),
        ];
        $pusher->trigger('user-channel-' . $u->getId(), 'friend-accepted-event', $payloadToReceiver);

        return $this->json(['message' => 'Acceptee ! Conversation creee.'], 200);
    }
}
