<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AdzunaService
{
    private $httpClient;
   

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getSalaryStats(string $jobTitle): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.adzuna.com/v1/api/jobs/fr/history', [
                'query' => [
                    'app_id' => $this->appId,
                    'app_key' => $this->appKey,
                    'what' => $jobTitle,
                    'months' => 12
                ]
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            return ['error' => 'Impossible de récupérer les données'];
        }
    }
}