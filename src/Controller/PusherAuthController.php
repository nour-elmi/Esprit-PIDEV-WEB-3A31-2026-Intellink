<?php

namespace App\Controller;

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
        $user = $this->getUser();
        if (!$user) {
            return new Response('Forbidden', 403);
        }

        $socketId = $request->request->get('socket_id');
        $channelName = $request->request->get('channel_name');

        $pusher = new Pusher(
            $_ENV['PUSHER_KEY'],
            $_ENV['PUSHER_SECRET'],
            $_ENV['PUSHER_APP_ID'],
            ['cluster' => $_ENV['PUSHER_CLUSTER'], 'useTLS' => true, 'curl_options' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]]
        );

        $presenceData = [
            'name' => $user->getNom(),
        ];

        $auth = $pusher->presenceAuth($channelName, $socketId, $user->getId(), $presenceData);

        return new Response($auth, 200, ['Content-Type' => 'application/json']);
    }
}
