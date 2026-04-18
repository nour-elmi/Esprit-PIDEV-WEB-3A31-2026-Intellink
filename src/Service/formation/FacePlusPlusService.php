<?php

namespace App\Service\formation;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FacePlusPlusService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    public function analyzeFrames(string $referenceImage, string $currentImage): array
    {
        $apiKey = (string) ($_ENV['FACEPP_API_KEY'] ?? '');
        $apiSecret = (string) ($_ENV['FACEPP_API_SECRET'] ?? '');
        $baseUrl = rtrim((string) ($_ENV['FACEPP_BASE_URL'] ?? 'https://api-us.faceplusplus.com'), '/');
        $minConfidence = (float) ($_ENV['FACEPP_COMPARE_MIN_CONFIDENCE'] ?? 70);
        $requestTimeout = $this->normalizeTimeout((float) ($_ENV['FACEPP_TIMEOUT'] ?? 8.0));
        $retryCount = max(0, (int) ($_ENV['FACEPP_RETRY_COUNT'] ?? 2));
        $retryDelayMs = max(0, (int) ($_ENV['FACEPP_RETRY_DELAY_MS'] ?? 300));

        if ($apiKey === '' || $apiSecret === '') {
            return ['ok' => false, 'error' => 'Face++ non configure'];
        }

        $reference = $this->normalizeBase64($referenceImage);
        $current = $this->normalizeBase64($currentImage);
        if ($reference === '' || $current === '') {
            return ['ok' => false, 'error' => 'Images invalides'];
        }

        try {
            $detectResult = $this->detectFaceCount(
                $baseUrl,
                $apiKey,
                $apiSecret,
                $current,
                $requestTimeout,
                $retryCount,
                $retryDelayMs
            );
            if (!($detectResult['ok'] ?? false)) {
                return $detectResult;
            }

            $faceCount = (int) ($detectResult['faceCount'] ?? 0);
            if ($faceCount !== 1) {
                return [
                    'ok' => true,
                    'faceCount' => $faceCount,
                    'samePerson' => null,
                    'confidence' => null,
                    'threshold' => $minConfidence,
                ];
            }

            $compareResponse = $this->requestWithRetry(
                $baseUrl . '/facepp/v3/compare',
                [
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                    'image_base64_1' => $reference,
                    'image_base64_2' => $current,
                ],
                $requestTimeout,
                $retryCount,
                $retryDelayMs
            );
            $compareData = $compareResponse->toArray(false);

            if (($compareResponse->getStatusCode() >= 400) || isset($compareData['error_message'])) {
                return [
                    'ok' => false,
                    'error' => $this->formatFaceppError((string) ($compareData['error_message'] ?? 'Erreur compare Face++')),
                ];
            }

            $confidence = (float) ($compareData['confidence'] ?? 0.0);
            $thresholds = is_array($compareData['thresholds'] ?? null) ? $compareData['thresholds'] : [];
            $apiThreshold = isset($thresholds['1e-5']) ? (float) $thresholds['1e-5'] : $minConfidence;
            $effectiveThreshold = max($minConfidence, $apiThreshold);

            return [
                'ok' => true,
                'faceCount' => $faceCount,
                'samePerson' => $confidence >= $effectiveThreshold,
                'confidence' => round($confidence, 3),
                'threshold' => round($effectiveThreshold, 3),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Face++ indisponible (reseau/timeout).'];
        }
    }

    public function analyzeCurrentFrame(string $currentImage): array
    {
        $apiKey = (string) ($_ENV['FACEPP_API_KEY'] ?? '');
        $apiSecret = (string) ($_ENV['FACEPP_API_SECRET'] ?? '');
        $baseUrl = rtrim((string) ($_ENV['FACEPP_BASE_URL'] ?? 'https://api-us.faceplusplus.com'), '/');
        $minConfidence = (float) ($_ENV['FACEPP_COMPARE_MIN_CONFIDENCE'] ?? 70);
        $requestTimeout = $this->normalizeTimeout((float) ($_ENV['FACEPP_TIMEOUT'] ?? 8.0));
        $retryCount = max(0, (int) ($_ENV['FACEPP_RETRY_COUNT'] ?? 2));
        $retryDelayMs = max(0, (int) ($_ENV['FACEPP_RETRY_DELAY_MS'] ?? 300));

        if ($apiKey === '' || $apiSecret === '') {
            return ['ok' => false, 'error' => 'Face++ non configure'];
        }

        $current = $this->normalizeBase64($currentImage);
        if ($current === '') {
            return ['ok' => false, 'error' => 'Image invalide'];
        }

        try {
            $detectResult = $this->detectFaceCount(
                $baseUrl,
                $apiKey,
                $apiSecret,
                $current,
                $requestTimeout,
                $retryCount,
                $retryDelayMs
            );
            if (!($detectResult['ok'] ?? false)) {
                return $detectResult;
            }

            return [
                'ok' => true,
                'faceCount' => (int) ($detectResult['faceCount'] ?? 0),
                'samePerson' => null,
                'confidence' => null,
                'threshold' => $minConfidence,
            ];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Face++ indisponible (reseau/timeout).'];
        }
    }

    private function normalizeBase64(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (str_contains($trimmed, ',')) {
            $parts = explode(',', $trimmed, 2);
            $trimmed = $parts[1] ?? '';
        }

        return preg_replace('/\s+/', '', $trimmed) ?? '';
    }

    private function normalizeTimeout(float $timeout): float
    {
        if ($timeout <= 0) {
            return 8.0;
        }

        return max(3.0, min($timeout, 20.0));
    }

    private function requestWithRetry(
        string $url,
        array $body,
        float $timeout,
        int $retryCount,
        int $retryDelayMs
    ): ResponseInterface {
        $attempt = 0;
        $maxAttempt = 1 + $retryCount;
        $lastResponse = null;

        while ($attempt < $maxAttempt) {
            $attempt++;
            $response = $this->httpClient->request('POST', $url, [
                'body' => $body,
                'timeout' => $timeout,
            ]);

            $status = 0;
            try {
                $status = $response->getStatusCode();
            } catch (\Throwable) {
                if ($attempt >= $maxAttempt) {
                    throw new \RuntimeException('Face++ timeout/reseau');
                }
                if ($retryDelayMs > 0) {
                    usleep($retryDelayMs * 1000);
                }
                continue;
            }

            $lastResponse = $response;
            $retryable = $status === 429 || $status >= 500;
            if (!$retryable || $attempt >= $maxAttempt) {
                return $response;
            }

            if ($retryDelayMs > 0) {
                usleep($retryDelayMs * 1000);
            }
        }

        if ($lastResponse instanceof ResponseInterface) {
            return $lastResponse;
        }

        throw new \RuntimeException('Face++ indisponible');
    }

    private function detectFaceCount(
        string $baseUrl,
        string $apiKey,
        string $apiSecret,
        string $currentBase64,
        float $requestTimeout,
        int $retryCount,
        int $retryDelayMs
    ): array {
        $detectResponse = $this->requestWithRetry(
            $baseUrl . '/facepp/v3/detect',
            [
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'image_base64' => $currentBase64,
                'return_landmark' => 0,
            ],
            $requestTimeout,
            $retryCount,
            $retryDelayMs
        );
        $detectData = $detectResponse->toArray(false);

        if (($detectResponse->getStatusCode() >= 400) || isset($detectData['error_message'])) {
            return [
                'ok' => false,
                'error' => $this->formatFaceppError((string) ($detectData['error_message'] ?? 'Erreur detect Face++')),
            ];
        }

        return [
            'ok' => true,
            'faceCount' => (int) ($detectData['face_num'] ?? 0),
        ];
    }

    private function formatFaceppError(string $error): string
    {
        $e = strtoupper(trim($error));
        if ($e === '') {
            return 'Erreur Face++.';
        }
        if (str_contains($e, 'CONCURRENCY_LIMIT_EXCEEDED') || str_contains($e, 'RATE_LIMIT_EXCEEDED') || str_contains($e, 'TOO MANY REQUESTS')) {
            return 'Face++ limite atteinte (trop de requetes). Reessayez dans quelques secondes.';
        }
        if (str_contains($e, 'INSUFFICIENT_BALANCE')) {
            return 'Face++ quota/credits insuffisants.';
        }
        if (str_contains($e, 'AUTHENTICATION_FAILED') || str_contains($e, 'INVALID_API_KEY') || str_contains($e, 'API KEY')) {
            return 'Face++ cle API invalide ou non autorisee.';
        }

        return 'Face++: ' . trim($error);
    }
}
