<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RecaptchaVerifier
{
    private HttpClientInterface $httpClient;
    private string $recaptchaSecret;

    public function __construct(HttpClientInterface $httpClient, string $recaptchaSecret = '')
    {
        $this->httpClient = $httpClient;
        $this->recaptchaSecret = $recaptchaSecret;
    }

    public function verify(?string $token): bool
    {
        if (!$token || $this->recaptchaSecret === '') {
            return false;
        }

        $response = $this->httpClient->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $this->recaptchaSecret,
                'response' => $token,
            ],
        ]);

        $data = $response->toArray(false);

        return !empty($data['success']);
    }
}
