<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AdzunaService
{
    private $httpClient;
    private $appId = 'a509e1f2'; 
    private $appKey = '9bb8a7e4e96a4ffcd06e71e35942ac70'; 
    

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