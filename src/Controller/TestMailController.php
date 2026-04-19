<?php

namespace App\Controller;

use App\Service\MailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TestMailController extends AbstractController
{
    #[Route('/test-mail', name: 'app_test_mail')]
    public function test(MailService $mailService): Response
    {
        $to = 'bjaouimohamed2011@gmail.com';

        try {
            $mailService->send(
                $to,
                'Test Symfony Mail',
                '<h2>Email OK</h2><p>It works!</p>'
            );

            return new Response('MAIL SENT TO: ' . $to);
        } catch (\Throwable $e) {
            return new Response(
                'MAIL ERROR: ' . $e->getMessage() . "\n\n" .
                'CLASS: ' . get_class($e)
            );
        }
    }
}