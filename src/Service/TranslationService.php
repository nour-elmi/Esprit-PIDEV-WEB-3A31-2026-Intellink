<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslationService
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {
    }

    public function translate(string $text, string $sourceLang, string $targetLang): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        // MyMemory does not support "auto"
        $src = ($sourceLang === '' || strtolower($sourceLang) === 'auto') ? 'en' : $sourceLang;
        $tgt = trim($targetLang) !== '' ? $targetLang : 'en';

        $response = $this->httpClient->request('GET', 'https://api.mymemory.translated.net/get', [
            'query' => [
                'q' => $text,
                'langpair' => $src . '|' . $tgt,
            ],
            'timeout' => 20,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Translation API error.');
        }

        $data = $response->toArray(false);

        return trim($data['responseData']['translatedText'] ?? '');
    }
}