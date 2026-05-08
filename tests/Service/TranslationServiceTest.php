<?php

namespace App\Tests\Service;

use App\Service\TranslationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TranslationServiceTest extends TestCase
{
    public function testTranslateReturnsTranslatedText(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'responseData' => [
                    'translatedText' => 'Bonjour',
                ],
            ]), [
                'http_code' => 200,
            ]),
        ]);

        $service = new TranslationService($client);

        self::assertSame('Bonjour', $service->translate('Hello', 'en', 'fr'));
    }

    public function testTranslateReturnsEmptyForEmptyInput(): void
    {
        $client = new MockHttpClient();
        $service = new TranslationService($client);

        self::assertSame('', $service->translate('', 'en', 'fr'));
    }

    public function testTranslateThrowsExceptionWhenApiFails(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ]);

        $service = new TranslationService($client);

        $this->expectException(\RuntimeException::class);

        $service->translate('Hello', 'en', 'fr');
    }
}