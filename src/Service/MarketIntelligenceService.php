<?php

namespace App\Service;

use App\Entity\Emploi;

class MarketIntelligenceService
{
    private AdzunaService $adzunaService;

    public function __construct(AdzunaService $adzunaService)
    {
        $this->adzunaService = $adzunaService;
    }

    /**
     * @return array{market_avg:int|string, candidats_count:int, top_skills:list<string>}
     */
    public function analyzeGap(Emploi $emploi): array
    {
        $marketData = $this->adzunaService->getSalaryStats((string) ($emploi->getTitre() ?? ''));
        
        // Extraction des compétences des candidats (colonne 'skills' de ta table participation)
        $candidatsSkills = [];
        foreach ($emploi->getListeParticipations() as $participation) {
            if ($participation->getSkills()) {
                // On transforme la chaîne de texte en tableau de mots-clés uniques
                $skills = explode(',', strtolower($participation->getSkills()));
                $candidatsSkills = array_unique(array_merge($candidatsSkills, array_map('trim', $skills)));
            }
        }

        return [
            'market_avg' => !empty($marketData['month']) ? end($marketData['month']) : 'N/A',
            'candidats_count' => count($emploi->getListeParticipations()),
            'top_skills' => array_slice($candidatsSkills, 0, 5), // Top 5 compétences trouvées
        ];
    }
}
