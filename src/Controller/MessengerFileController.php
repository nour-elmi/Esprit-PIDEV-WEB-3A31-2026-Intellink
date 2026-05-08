<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\FriendRequest;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MessengerFileController extends AbstractController
{
    #[Route('/api/messenger/unfriend/{conversationId}', name: 'api_messenger_unfriend', methods: ['POST'])]
    public function unfriend(int $conversationId, EntityManagerInterface $em): JsonResponse
    {
        // CHANGEMENT: typer explicitement l'utilisateur connecte pour PHPStan.
        // Ancien code (garde):
        // $currentUser = $this->getUser();
        // if (!$currentUser) return $this->json(['error' => 'Non authentifie'], 401);
        $currentUser = $this->getUser();
        if (!$currentUser instanceof Utilisateur) {
            return $this->json(['error' => 'Non authentifie'], 401);
        }

        $conversation = $em->getRepository(Conversation::class)->find($conversationId);
        if (!$conversation) {
            return $this->json(['error' => 'Conv non trouvee'], 404);
        }

        $otherUser = null;
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getId() !== $currentUser->getId()) {
                $otherUser = $p;
                break;
            }
        }

        if (!$otherUser) {
            return $this->json(['error' => 'Autre utilisateur introuvable'], 400);
        }

        $repo = $em->getRepository(FriendRequest::class);
        $friendRequest = $repo->findOneBy(['requester' => $currentUser, 'receiver' => $otherUser, 'status' => 'ACCEPTED']);
        if (!$friendRequest) {
            $friendRequest = $repo->findOneBy(['requester' => $otherUser, 'receiver' => $currentUser, 'status' => 'ACCEPTED']);
        }

        if ($friendRequest) {
            $em->remove($friendRequest);
            $em->remove($conversation);
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

            $payload = [
                'type' => 'unfriend',
                'removerId' => $currentUser->getId(),
                'removedId' => $otherUser->getId(),
                'conversationId' => $conversationId,
            ];

            $pusher->trigger('user-channel-' . $currentUser->getId(), 'unfriend-event', $payload);
            $pusher->trigger('user-channel-' . $otherUser->getId(), 'unfriend-event', $payload);

            return $this->json(['success' => true, 'message' => 'Ami supprime avec succes']);
        }

        return $this->json(['success' => false, 'message' => 'Vous n\'etes pas amis']);
    }

    #[Route('/api/messenger/shared-files/{conversationId}', name: 'api_messenger_shared_files', methods: ['GET'])]
    public function getSharedFiles(int $conversationId, EntityManagerInterface $em): JsonResponse
    {
        // CHANGEMENT: typer explicitement l'utilisateur connecte pour PHPStan.
        // Ancien code (garde):
        // $currentUser = $this->getUser();
        // if (!$currentUser) return $this->json(['error' => 'Non authentifie'], 401);
        $currentUser = $this->getUser();
        if (!$currentUser instanceof Utilisateur) {
            return $this->json(['error' => 'Non authentifie'], 401);
        }

        $conversation = $em->getRepository(Conversation::class)->find($conversationId);
        if (!$conversation) {
            return $this->json(['error' => 'Conv non trouvee'], 404);
        }

        $files = [];
        $seen = [];
        foreach ($conversation->getMessages() as $msg) {
            if ($msg->getAttachment()) {
                $url = '/uploads/messenger/' . $msg->getAttachment();
                if (!in_array($url, $seen, true)) {
                    $seen[] = $url;
                    $expediteur = $msg->getExpediteur(); // CHANGEMENT
                    $files[] = [
                        'fileName' => basename($msg->getAttachment()),
                        'url' => $url,
                        'sender' => $expediteur ? $expediteur->getNom() : 'Utilisateur',
                    ];
                }
            }
        }

        return $this->json(['files' => $files]);
    }
}
