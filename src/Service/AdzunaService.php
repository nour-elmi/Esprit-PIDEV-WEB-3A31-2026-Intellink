<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AdzunaService
{
    // CHANGEMENT: ajout des types explicites pour PHPStan.
    // Ancien code (garde): private $httpClient;
    private HttpClientInterface $httpClient;

    // CHANGEMENT: ajout des types explicites pour PHPStan.
    // Ancien code (garde): private $appId = 'a509e1f2';
    private string $appId = 'a509e1f2';

    // CHANGEMENT: ajout des types explicites pour PHPStan.
    // Ancien code (garde): private $appKey = '9bb8a7e4e96a4ffcd06e71e35942ac70';
    private string $appKey = '9bb8a7e4e96a4ffcd06e71e35942ac70';
    

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        
    }

    /**
     * CHANGEMENT: type de retour iterable precis pour PHPStan.
     * Ancien code (garde): public function getSalaryStats(string $jobTitle): array
     *
     * @return array<string, mixed>
     */
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
