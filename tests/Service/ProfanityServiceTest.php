<?php

namespace App\Tests\Service;

use App\Service\ProfanityService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ProfanityServiceTest extends TestCase
{
    public function testCleanTextReturnsCleanedResult(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'result' => 'hello ***',
            ]), [
                'http_code' => 200,
            ]),
        ]);

        $service = new ProfanityService($client);

        self::assertSame('hello ***', $service->cleanText('hello badword'));
    }

    public function testCleanTextReturnsEmptyForEmptyInput(): void
    {
        $client = new MockHttpClient();
        $service = new ProfanityService($client);

        self::assertSame('', $service->cleanText(''));
    }

    public function testContainsProfanityReturnsTrueWhenTextChanges(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'result' => 'hello ***',
            ]), [
                'http_code' => 200,
            ]),
        ]);

        $service = new ProfanityService($client);

        self::assertTrue($service->containsProfanity('hello badword'));
    }

    public function testCleanTextThrowsExceptionWhenApiFails(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ]);

        $service = new ProfanityService($client);

        $this->expectException(\RuntimeException::class);

        $service->cleanText('hello');
    }
}