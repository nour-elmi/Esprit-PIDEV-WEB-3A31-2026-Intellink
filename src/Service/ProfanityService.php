<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ProfanityService
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {
    }

    public function cleanText(?string $text): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        $response = $this->httpClient->request('GET', 'https://www.purgomalum.com/service/json', [
            'query' => [
                'text' => $text,
            ],
            'timeout' => 15,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('PurgoMalum error.');
        }

        $data = $response->toArray(false);

        return trim((string) ($data['result'] ?? ''));
    }

    public function containsProfanity(?string $text): bool
    {
        $original = trim((string) $text);
        $cleaned = $this->cleanText($original);

        return $original !== '' && $cleaned !== '' && $original !== $cleaned;
    }
}