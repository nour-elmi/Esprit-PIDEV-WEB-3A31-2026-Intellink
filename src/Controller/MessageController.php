<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use Pusher\Pusher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MessageController extends AbstractController
{
    #[Route('/api/message/send/{conversationId}', name: 'api_message_send', methods: ['POST'])]
    public function sendMessage(
        int $conversationId,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return new JsonResponse(['error' => 'Non connectÃƒÂ©'], 403);
        }

        // 1. RÃƒÂ©cupÃƒÂ©rer la conversation et le texte du message
        $conversation = $em->getRepository(Conversation::class)->find($conversationId);
        if (!$conversation) {
            return new JsonResponse(['error' => 'Conversation introuvable'], 404);
        }

        // On gÃƒÂ¨re d'abord FormData (Fichier + texte)
        $texte = $request->request->get('contenu') ?? '';
        $file = $request->files->get('attachment');

        // Si le body est vide on essaye le JSON
        if (empty($texte) && !$file && $request->getContent()) {
            $data = json_decode($request->getContent(), true);
            if ($data) {
                $texte = $data['contenu'] ?? '';
            }
        }

        if (empty(trim($texte)) && !$file) {
            return new JsonResponse(['error' => 'Message vide'], 400);
        }

        $message = new Message();
        $message->setConversation($conversation);
        $message->setExpediteur($currentUser);
        $message->setContenu($texte);

        if ($file) {
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/messenger';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }
            $filename = uniqid() . '.' . $file->guessExtension();
            $file->move($uploadsDir, $filename);
            $message->setAttachment($filename);
        }

        $em->persist($message);
        $em->flush();

        // 3. Ã°Å¸â€Â´ LA MAGIE PUSHER : On envoie l'alerte en temps rÃƒÂ©el ! Ã°Å¸â€Â´
        $pusher = new Pusher(
            $_ENV['PUSHER_KEY'],
            $_ENV['PUSHER_SECRET'],
            $_ENV['PUSHER_APP_ID'],
            ['cluster' => $_ENV['PUSHER_CLUSTER'], 'useTLS' => true, 'curl_options' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]]
        );

        // On prÃƒÂ©pare les donnÃƒÂ©es ÃƒÂ  envoyer au navigateur
        $donneesPusher = [
            'id' => $message->getId(),
            'contenu' => $message->getContenu(),
            'expediteur_id' => $currentUser->getId(),
            'expediteur_nom' => $currentUser->getNom(),
            'conversation_id' => $conversation->getId(),
            'attachment' => $message->getAttachment(),
            'heure' => $message->getCreatedAt()->format('H:i') // Ã°Å¸Å¸Â¢ NOUVEAU : On envoie l'heure exacte
        ];

        // Ã°Å¸Å¸Â¢ DÃƒâ€°CLENCHEUR 1 : Pour mettre ÃƒÂ  jour la fenÃƒÂªtre de chat ouverte
        $pusher->trigger('chat-conversation-' . $conversation->getId(), 'nouveau-message', $donneesPusher);

        // Ã°Å¸Å¸Â¢ DÃƒâ€°CLENCHEUR 2 (NOUVEAU) : Le canal GLOBAL pour la notification de l'autre utilisateur (pastille +99)
        $receiver = null;
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getId() !== $currentUser->getId()) { 
                $receiver = $p; 
                break; 
            }
        }
        
        if ($receiver) {
            $pusher->trigger('user-channel-' . $receiver->getId(), 'notification', $donneesPusher);
        }

        // On rÃƒÆ’Ã‚Â©pond au JS en incluant le nouveau nom du fichier
        return new JsonResponse([
            'success' => true,
            'message' => $donneesPusher
        ]);
    }

    #[Route('/api/message/typing/{conversationId}', name: 'api_message_typing', methods: ['POST'])]
    public function typing(int $conversationId): JsonResponse
    {
        // On prÃƒÂ©vient Pusher que cet utilisateur est en train d'ÃƒÂ©crire
        $pusher = new Pusher(
            $_ENV['PUSHER_KEY'], $_ENV['PUSHER_SECRET'], $_ENV['PUSHER_APP_ID'],
            ['cluster' => $_ENV['PUSHER_CLUSTER'], 'useTLS' => true, 'curl_options' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]]
        );

        $pusher->trigger('chat-conversation-' . $conversationId, 'user-typing', [
            'user_id' => $this->getUser()->getId()
        ]);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/message/history/{conversationId}', name: 'api_message_history', methods: ['GET'])]
    public function getHistory(int $conversationId, EntityManagerInterface $em): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return new JsonResponse(['error' => 'Non connectÃƒÂ©'], 403);
        }

        $conversation = $em->getRepository(Conversation::class)->find($conversationId);
        if (!$conversation) {
            return new JsonResponse(['error' => 'Conversation introuvable'], 404);
        }

        // SÃƒÂ©curitÃƒÂ© : VÃƒÂ©rifier que l'utilisateur fait bien partie de cette conversation
        if (!$conversation->getParticipants()->contains($currentUser)) {
            return new JsonResponse(['error' => 'AccÃƒÂ¨s refusÃƒÂ©'], 403);
        }

        // RÃƒÂ©cupÃƒÂ©rer les messages triÃƒÂ©s par date (du plus ancien au plus rÃƒÂ©cent)
        $messages = $em->getRepository(Message::class)->findBy(
            ['conversation' => $conversation],
            ['createdAt' => 'ASC'] // ASC = Ascendant (chronologique)
        );

        $data = [];
        foreach ($messages as $msg) {
            $data[] = [
                'id' => $msg->getId(),
                'contenu' => $msg->getContenu(),
                'expediteur_id' => $msg->getExpediteur()->getId(),
                'attachment' => $msg->getAttachment(),
                'isRead' => method_exists($msg, 'isRead') ? $msg->isRead() : false,
                'heure' => $msg->getCreatedAt()->format('H:i'), 
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/message/read/{conversationId}', name: 'api_message_read', methods: ['POST'])]
    public function markAsRead(int $conversationId, EntityManagerInterface $em): JsonResponse
    {
        $conversation = $em->getRepository(Conversation::class)->find($conversationId);
        if ($conversation && $this->getUser()) {
            $messages = $em->getRepository(Message::class)->findBy([
                'conversation' => $conversation,
                'isRead' => false
            ]);

            $readCount = 0;
            foreach ($messages as $msg) {
                if ($msg->getExpediteur() !== $this->getUser()) {
                    $msg->setIsRead(true);
                    $readCount++;
                }
            }
            
            if ($readCount > 0) {
                $em->flush();
                
                try {
                    $pusher = new \Pusher\Pusher(
                        $_ENV['PUSHER_KEY'],
                        $_ENV['PUSHER_SECRET'],
                        $_ENV['PUSHER_APP_ID'],
                        ['cluster' => $_ENV['PUSHER_CLUSTER'], 'useTLS' => true, 'curl_options' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]]
                    );

                    $pusher->trigger('chat-conversation-' . $conversation->getId(), 'messages-read', [
                        'reader_id' => $this->getUser()->getId()
                    ]);
                } catch (\Throwable $e) {}
            }
        }
        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/ai/chat', name: 'api_ai_chat', methods: ['POST'])]
    public function intelLinkAi(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        // GÃƒÂ©rer le JSON ou le form-data
        $contentType = $request->headers->get('Content-Type', '');
        
        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode($request->getContent(), true);
            $userMessage = $data['contenu'] ?? '';
        } else {
            $userMessage = $request->request->get('contenu', '');
        }

        $userMessage = trim($userMessage);

        if (empty($userMessage)) {
            return new JsonResponse(['reponse' => "Je n'ai rien entendu ! Ã°Å¸Â¤â€“"]);
        }

        // 1. RÃƒÂ©cupÃƒÂ©ration de la clÃƒÂ© API depuis le .env
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? null;
        if (!$apiKey) {
            return new JsonResponse(['reponse' => "Erreur : ClÃƒÂ© API Gemini manquante dans le serveur."]);
        }

        // 2. PrÃƒÂ©paration de la personnalitÃƒÂ© du Bot (Prompt Engineering)
        $contexte = "Tu es IntelLink-AI, l'assistant virtuel exclusif de la plateforme de rÃƒÂ©seau professionnel Intel_Link. 
        Ton but est d'aider les utilisateurs ÃƒÂ  dÃƒÂ©velopper leur carriÃƒÂ¨re, trouver des stages, et amÃƒÂ©liorer leur rÃƒÂ©seau. 
        Tu dois rÃƒÂ©pondre de maniÃƒÂ¨re trÃƒÂ¨s concise (2 ou 3 phrases maximum), professionnelle, et chaleureuse. 
        Tu peux utiliser des emojis. Voici le message de l'utilisateur : \n\n";

        $promptComplet = $contexte . $userMessage;

      // 3. Appel ÃƒÂ  l'API Gemini (Le modÃƒÂ¨le actuel et actif !)
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;

        try {
            $response = $httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'verify_peer' => false,
                'verify_host' => false,
                // Ã°Å¸â€Â´ LA SOLUTION MAGIQUE EST ICI : Forcer le HTTP/1.1
                'http_version' => '1.1', 
                // --------------------------------------------------
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $promptComplet]
                            ]
                        ]
                    ]
                ]
            ]);

            // 4. Extraction de la rÃƒÂ©ponse de Gemini
            $content = $response->toArray();
            $aiResponse = $content['candidates'][0]['content']['parts'][0]['text'] ?? "DÃƒÂ©solÃƒÂ©, je n'ai pas de rÃƒÂ©ponse ÃƒÂ  formuler.";

            $aiResponse = str_replace('**', '', $aiResponse);

            return new JsonResponse(['reponse' => $aiResponse]);

        } catch (\Exception $e) {
            return new JsonResponse(['reponse' => "Erreur technique : " . $e->getMessage()]);
        }
    }
}

