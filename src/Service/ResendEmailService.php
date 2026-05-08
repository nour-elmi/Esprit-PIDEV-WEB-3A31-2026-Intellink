<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ResendEmailService
{
    private string $apiKey = 're_cvE6GNAU_NNqoco873TdiECBXih8DnNvT';
    private string $from = 'Forum <onboarding@resend.dev>';

    public function __construct(
        private HttpClientInterface $httpClient
    ) {
    }

    public function send(string $to, string $subject, string $html): bool
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.resend.com/emails', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'from' => $this->from,
                    'to' => [$to],
                    'subject' => $subject,
                    'html' => $html,
                ],
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable $e) {
            return false;
        }
    }
}