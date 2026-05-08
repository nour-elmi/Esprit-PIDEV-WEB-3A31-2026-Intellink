<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Pusher\Pusher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PusherAuthController extends AbstractController
{
    #[Route('/api/pusher/auth', name: 'api_pusher_auth', methods: ['POST'])]
    public function pusherAuth(Request $request): Response
    {
        // CHANGEMENT: typer explicitement l'utilisateur connecte pour PHPStan.
        // Ancien code (garde):
        // $user = $this->getUser();
        // if (!$user) {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return new Response('Forbidden', 403);
        }

        $socketIdRaw = $request->request->get('socket_id'); // CHANGEMENT
        $channelNameRaw = $request->request->get('channel_name'); // CHANGEMENT
        if (!is_string($socketIdRaw) || !is_string($channelNameRaw) || $socketIdRaw === '' || $channelNameRaw === '') {
            return new Response('Invalid payload', 400);
        }
        $socketId = $socketIdRaw;
        $channelName = $channelNameRaw;

        $pusher = new Pusher(
            $_ENV['PUSHER_KEY'],
            $_ENV['PUSHER_SECRET'],
            $_ENV['PUSHER_APP_ID'],
            ['cluster' => $_ENV['PUSHER_CLUSTER'], 'useTLS' => true, 'curl_options' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]]
        );

        $presenceData = [
            'name' => $user->getNom(),
        ];

        // CHANGEMENT: presenceAuth attend un user_id de type string.
        // Ancien code (garde): $auth = $pusher->presenceAuth($channelName, $socketId, $user->getId(), $presenceData);
        $auth = $pusher->presenceAuth($channelName, $socketId, (string) $user->getId(), $presenceData);

        return new Response($auth, 200, ['Content-Type' => 'application/json']);
    }
}
