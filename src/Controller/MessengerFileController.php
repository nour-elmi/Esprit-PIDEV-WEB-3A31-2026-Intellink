<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\FriendRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\Slugger\SluggerInterface;

final class MessengerFileController extends AbstractController
{
    #[Route('/api/messenger/unfriend/{conversationId}', name: 'api_messenger_unfriend', methods: ['POST'])]
    public function unfriend(int $conversationId, EntityManagerInterface $em): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) return $this->json(['error' => 'Non authentifié'], 401);

        $conversation = $em->getRepository(Conversation::class)->find($conversationId);
        if (!$conversation) return $this->json(['error' => 'Conv non trouvée'], 404);

        $otherUser = null;
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getId() !== $currentUser->getId()) {
                $otherUser = $p;
                break;
            }
        }

        if (!$otherUser) return $this->json(['error' => 'Autre utilisateur introuvable'], 400);

        // Trouver la relation d'amitié (FriendRequest) et la supprimer
        $repo = $em->getRepository(FriendRequest::class);
        $request = $repo->findOneBy(['requester' => $currentUser, 'receiver' => $otherUser, 'status' => 'ACCEPTED']);
        if (!$request) {
            $request = $repo->findOneBy(['requester' => $otherUser, 'receiver' => $currentUser, 'status' => 'ACCEPTED']);
        }

        if ($request) {
            $em->remove($request);
            
            // Pour être sûr qu'ils n'apparaissent plus dans les discussions récentes,
            // on supprime aussi la conversation associée.
            $em->remove($conversation);
            
            $em->flush();

            // Pusher Event to trigger immediate DOM deletion for BOTH users
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

            $payload = [
                'type' => 'unfriend',
                'removerId' => $currentUser->getId(),
                'removedId' => $otherUser->getId(),
                'conversationId' => $conversationId
            ];

            $pusher->trigger('user-channel-' . $currentUser->getId(), 'unfriend-event', $payload);
            $pusher->trigger('user-channel-' . $otherUser->getId(), 'unfriend-event', $payload);

            return $this->json(['success' => true, 'message' => 'Ami supprimé avec succès']);
        }

        return $this->json(['success' => false, 'message' => 'Vous n\'êtes pas amis']);
    }

    #[Route('/api/messenger/shared-files/{conversationId}', name: 'api_messenger_shared_files', methods: ['GET'])]
    public function getSharedFiles(int $conversationId, EntityManagerInterface $em): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) return $this->json(['error' => 'Non authentifié'], 401);

        $conversation = $em->getRepository(Conversation::class)->find($conversationId);
        if (!$conversation) return $this->json(['error' => 'Conv non trouvée'], 404);

        $files = [];
        $seen = [];
        foreach ($conversation->getMessages() as $msg) {
            if ($msg->getAttachment()) {
                $url = '/uploads/messenger/' . $msg->getAttachment();
                if (!in_array($url, $seen)) {
                    $seen[] = $url;
                    $files[] = [
                        'fileName' => basename($msg->getAttachment()),
                        'url' => $url,
                        'sender' => $msg->getExpediteur()->getNom()
                    ];
                }
            }
        }

        return $this->json(['files' => $files]);
    }
}
