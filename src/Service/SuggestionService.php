<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SuggestionService
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {
    }

    public function getTechSuggestions(int $limit = 6): array
    {
        $response = $this->httpClient->request('GET', 'https://zenquotes.io/api/quotes', [
            'timeout' => 15,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('ZenQuotes error.');
        }

        $data = $response->toArray(false);

        $quotes = [];
        foreach ($data as $item) {
            $quote = trim((string) ($item['q'] ?? ''));
            $author = trim((string) ($item['a'] ?? ''));

            if ($quote !== '') {
                $quotes[] = sprintf('"%s" — %s', $quote, $author ?: 'Unknown');
            }
        }

        $techQuotes = [];
        foreach ($quotes as $q) {
            $text = mb_strtolower($q);

            if (
                str_contains($text, 'technology') ||
                str_contains($text, 'tech') ||
                str_contains($text, 'code') ||
                str_contains($text, 'software') ||
                str_contains($text, 'developer') ||
                str_contains($text, 'computer') ||
                str_contains($text, 'innovation') ||
                str_contains($text, 'data')
            ) {
                $techQuotes[] = $q;
            }
        }

        if (empty($techQuotes)) {
            $techQuotes = $quotes;
        }

        return array_slice($techQuotes, 0, $limit);
    }
}