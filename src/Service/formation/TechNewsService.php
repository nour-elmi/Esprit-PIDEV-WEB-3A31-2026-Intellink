<?php

namespace App\Service\formation;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TechNewsService
{
    private const CACHE_KEY = 'formation.tech_news.v1';
    private const CACHE_TTL = 900; // 15 minutes

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLatestTechNews(int $limit = 5): array
    {
        $limit = max(1, min(10, $limit));

        return $this->cache->get(self::CACHE_KEY . '.' . $limit, function (ItemInterface $item) use ($limit): array {
            $item->expiresAfter(self::CACHE_TTL);

            try {
                $response = $this->httpClient->request('GET', 'https://hn.algolia.com/api/v1/search', [
                    'query' => [
                        'tags' => 'front_page',
                        'hitsPerPage' => 30,
                    ],
                    'timeout' => 8,
                ]);

                $payload = $response->toArray(false);
                if (!isset($payload['hits']) || !is_array($payload['hits'])) {
                    return [];
                }

                $news = [];
                foreach ($payload['hits'] as $hit) {
                    if (count($news) >= $limit) {
                        break;
                    }

                    $title = trim((string) ($hit['title'] ?? ''));
                    $url = trim((string) ($hit['url'] ?? ''));
                    if ($title === '' || $url === '') {
                        continue;
                    }

                    $timestamp = isset($hit['created_at_i']) ? (int) $hit['created_at_i'] : 0;
                    $publishedAt = $timestamp > 0 ? (new \DateTimeImmutable())->setTimestamp($timestamp) : null;

                    $news[] = [
                        'title' => $title,
                        'url' => $url,
                        'source' => 'Hacker News',
                        'author' => (string) ($hit['author'] ?? 'unknown'),
                        'points' => isset($hit['points']) ? (int) $hit['points'] : 0,
                        'publishedAt' => $publishedAt,
                    ];
                }

                return $news;
            } catch (\Throwable) {
                return [];
            }
        });
    }
}

