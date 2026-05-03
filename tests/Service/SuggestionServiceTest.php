<?php

namespace App\Tests\Service;

use App\Service\SuggestionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SuggestionServiceTest extends TestCase
{
    public function testGetTechSuggestionsReturnsFilteredTechQuotes(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                [
                    'q' => 'Technology changes the world.',
                    'a' => 'Author One',
                ],
                [
                    'q' => 'Life is beautiful.',
                    'a' => 'Author Two',
                ],
                [
                    'q' => 'Software developers build the future.',
                    'a' => 'Author Three',
                ],
            ]), [
                'http_code' => 200,
            ]),
        ]);

        $service = new SuggestionService($client);

        $result = $service->getTechSuggestions(2);

        self::assertCount(2, $result);
        self::assertSame('"Technology changes the world." — Author One', $result[0]);
        self::assertSame('"Software developers build the future." — Author Three', $result[1]);
    }

    public function testGetTechSuggestionsUsesAllQuotesWhenNoTechQuotes(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                [
                    'q' => 'Life is beautiful.',
                    'a' => 'Author One',
                ],
            ]), [
                'http_code' => 200,
            ]),
        ]);

        $service = new SuggestionService($client);

        $result = $service->getTechSuggestions(1);

        self::assertSame(['"Life is beautiful." — Author One'], $result);
    }

    public function testGetTechSuggestionsThrowsExceptionWhenApiFails(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ]);

        $service = new SuggestionService($client);

        $this->expectException(\RuntimeException::class);

        $service->getTechSuggestions();
    }
}