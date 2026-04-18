<?php

namespace App\Service\formation;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class YouTubeTranscriptService
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array{ok: bool, transcript?: string, error?: string}
     */
    public function fetchTranscriptFromUrl(string $videoUrl): array
    {
        $videoId = $this->extractVideoId($videoUrl);
        if ($videoId === null) {
            return ['ok' => false, 'error' => 'URL YouTube invalide.'];
        }

        // 1) First try YouTube Data API (key-based).
        $apiResult = $this->fetchTextWithYouTubeApi($videoId);
        if (($apiResult['ok'] ?? false) === true) {
            return $apiResult;
        }

        // 2) Fallback to public captions extraction.
        try {
            $watchResponse = $this->httpClient->request('GET', 'https://www.youtube.com/watch', [
                'query' => ['v' => $videoId, 'hl' => 'fr'],
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                ],
            ]);
            $watchHtml = $watchResponse->getContent();
        } catch (\Throwable) {
            return ['ok' => false, 'error' => (string) ($apiResult['error'] ?? 'Impossible de charger la page YouTube.')];
        }

        $tracks = $this->extractCaptionTracks($watchHtml);
        if ($tracks === []) {
            return ['ok' => false, 'error' => (string) ($apiResult['error'] ?? 'Aucune transcription disponible pour cette video YouTube.')];
        }

        $trackUrl = $this->pickBestTrackUrl($tracks);
        if ($trackUrl === null) {
            return ['ok' => false, 'error' => (string) ($apiResult['error'] ?? 'Aucun track de sous-titres exploitable.')];
        }

        try {
            $captionsResponse = $this->httpClient->request('GET', $trackUrl, [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                ],
            ]);
            $captionsRaw = $captionsResponse->getContent();
        } catch (\Throwable) {
            return ['ok' => false, 'error' => (string) ($apiResult['error'] ?? 'Impossible de recuperer la transcription YouTube.')];
        }

        $transcript = $this->captionsToText($captionsRaw);
        if (trim($transcript) === '') {
            $vttUrl = $this->buildCaptionUrlWithVttFormat($trackUrl);
            if ($vttUrl !== $trackUrl) {
                try {
                    $captionsResponse = $this->httpClient->request('GET', $vttUrl, [
                        'timeout' => 15,
                        'headers' => [
                            'User-Agent' => 'Mozilla/5.0',
                            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                        ],
                    ]);
                    $captionsRaw = $captionsResponse->getContent();
                    $transcript = $this->captionsToText($captionsRaw);
                } catch (\Throwable) {
                    // keep empty transcript and return below
                }
            }
        }

        if (trim($transcript) === '') {
            return ['ok' => false, 'error' => (string) ($apiResult['error'] ?? 'Transcription YouTube introuvable.')];
        }

        return ['ok' => true, 'transcript' => $transcript];
    }

    public function isYouTubeUrl(string $url): bool
    {
        return $this->extractVideoId($url) !== null;
    }

    private function extractVideoId(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if (str_contains($host, 'youtu.be')) {
            $id = trim($path, '/');
            return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
        }

        if (str_contains($host, 'youtube.com')) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $id = (string) ($query['v'] ?? '');
            if (preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
                return $id;
            }

            if (preg_match('#/embed/([A-Za-z0-9_-]{11})#', $path, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * YouTube Data API with key:
     * - Uses videos.list to fetch snippet (title + description).
     * - Returns a usable textual source for quiz generation.
     *
     * @return array{ok: bool, transcript?: string, error?: string}
     */
    private function fetchTextWithYouTubeApi(string $videoId): array
    {
        $baseUrl = rtrim((string) ($_ENV['YOUTUBE_API_BASE_URL'] ?? 'https://www.googleapis.com/youtube/v3'), '/');
        $apiKey = trim((string) ($_ENV['YOUTUBE_API_KEY'] ?? ''));

        if ($baseUrl === '' || $apiKey === '') {
            return ['ok' => false, 'error' => 'Configuration YouTube API manquante (YOUTUBE_API_BASE_URL / YOUTUBE_API_KEY).'];
        }

        try {
            $response = $this->httpClient->request('GET', $baseUrl . '/videos', [
                'timeout' => 15,
                'query' => [
                    'part' => 'snippet',
                    'id' => $videoId,
                    'key' => $apiKey,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0',
                ],
            ]);

            $data = $response->toArray(false);
            $items = $data['items'] ?? [];
            if (!is_array($items) || $items === []) {
                return ['ok' => false, 'error' => 'YouTube API: video introuvable ou inaccessible.'];
            }

            $snippet = $items[0]['snippet'] ?? [];
            if (!is_array($snippet)) {
                return ['ok' => false, 'error' => 'YouTube API: snippet indisponible.'];
            }

            $title = trim((string) ($snippet['title'] ?? ''));
            $description = trim((string) ($snippet['description'] ?? ''));

            $text = trim($title . '. ' . $description);
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

            if ($text === '') {
                return ['ok' => false, 'error' => 'YouTube API: description vide, impossible de generer des questions.'];
            }

            return ['ok' => true, 'transcript' => $text];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'YouTube API indisponible ou cle invalide.'];
        }
    }

    /**
     * @return array<int, array{baseUrl?: string, languageCode?: string}>
     */
    private function extractCaptionTracks(string $watchHtml): array
    {
        if (preg_match('/"captionTracks"\s*:\s*(\[[^\]]*\])/s', $watchHtml, $m)) {
            $tracks = json_decode($m[1], true);
            if (is_array($tracks)) {
                return $tracks;
            }
        }

        if (preg_match('/ytInitialPlayerResponse\s*=\s*\{/s', $watchHtml, $m, PREG_OFFSET_CAPTURE)) {
            $start = (int) $m[0][1];
            $bracePos = strpos($watchHtml, '{', $start);
            if ($bracePos !== false) {
                $jsonObject = $this->extractBalancedJsonObject($watchHtml, $bracePos);
                if ($jsonObject !== null) {
                    $data = json_decode($jsonObject, true);
                    if (is_array($data)) {
                        $tracks = $data['captions']['playerCaptionsTracklistRenderer']['captionTracks'] ?? null;
                        if (is_array($tracks)) {
                            return $tracks;
                        }
                    }
                }
            }
        }

        return [];
    }

    /**
     * @param array<int, array{baseUrl?: string, languageCode?: string}> $tracks
     */
    private function pickBestTrackUrl(array $tracks): ?string
    {
        $best = null;
        foreach ($tracks as $track) {
            $url = $this->normalizeCaptionUrl((string) ($track['baseUrl'] ?? ''));
            if ($url === '') {
                continue;
            }

            $lang = mb_strtolower((string) ($track['languageCode'] ?? ''));
            if ($lang === 'fr' || str_starts_with($lang, 'fr-')) {
                return $url;
            }
            if ($best === null && ($lang === 'en' || str_starts_with($lang, 'en-'))) {
                $best = $url;
            }
            if ($best === null) {
                $best = $url;
            }
        }

        return $best;
    }

    private function normalizeCaptionUrl(string $url): string
    {
        $url = html_entity_decode($url, ENT_QUOTES);
        $url = str_replace('\u0026', '&', $url);

        return trim($url);
    }

    private function buildCaptionUrlWithVttFormat(string $url): string
    {
        $url = $this->normalizeCaptionUrl($url);
        if ($url === '') {
            return $url;
        }

        if (preg_match('/([?&])fmt=[^&]*/', $url)) {
            return preg_replace('/([?&])fmt=[^&]*/', '$1fmt=vtt', $url) ?? $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'fmt=vtt';
    }

    private function captionsToText(string $captionsRaw): string
    {
        $trimmed = ltrim($captionsRaw);
        if (str_starts_with($trimmed, 'WEBVTT')) {
            return $this->vttToText($captionsRaw);
        }
        if (str_starts_with($trimmed, '<?xml') || str_starts_with($trimmed, '<transcript')) {
            return $this->xmlToText($captionsRaw);
        }

        return $this->vttToText($captionsRaw);
    }

    private function xmlToText(string $xml): string
    {
        if (!preg_match_all('/<text\b[^>]*>(.*?)<\/text>/su', $xml, $matches)) {
            return '';
        }

        $parts = [];
        foreach ($matches[1] as $chunk) {
            $line = html_entity_decode(strip_tags((string) $chunk), ENT_QUOTES);
            $line = preg_replace('/\s+/u', ' ', $line) ?? $line;
            $line = trim($line);
            if ($line !== '') {
                $parts[] = $line;
            }
        }

        return trim(implode(' ', $parts));
    }

    private function extractBalancedJsonObject(string $content, int $startBracePos): ?string
    {
        $len = strlen($content);
        $depth = 0;
        $inString = false;
        $escape = false;

        for ($i = $startBracePos; $i < $len; $i++) {
            $char = $content[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($char === '\\') {
                    $escape = true;
                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === '{') {
                $depth++;
                continue;
            }
            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $startBracePos, $i - $startBracePos + 1);
                }
            }
        }

        return null;
    }

    private function vttToText(string $vtt): string
    {
        $lines = preg_split('/\R/u', $vtt) ?: [];
        $out = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($line === 'WEBVTT') {
                continue;
            }
            if (preg_match('/^\d+$/', $line)) {
                continue;
            }
            if (str_contains($line, '-->')) {
                continue;
            }
            if (preg_match('/^[A-Z][A-Z0-9_-]*$/', $line)) {
                continue;
            }

            $line = preg_replace('/<[^>]+>/', '', $line) ?? $line;
            $line = html_entity_decode($line, ENT_QUOTES);
            $line = preg_replace('/\s+/u', ' ', $line) ?? $line;
            $line = trim($line);

            if ($line !== '') {
                $out[] = $line;
            }
        }

        $text = implode(' ', $out);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}

