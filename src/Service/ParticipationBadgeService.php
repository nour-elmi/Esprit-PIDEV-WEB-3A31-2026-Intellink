<?php

namespace App\Service;

use App\Entity\Collaboration;

class ParticipationBadgeService
{
    /**
     * @param Collaboration[] $demandes
     * @return array{
     *   score:int,
     *   levelLabel:string,
     *   stats: array{total:int, accepted:int, pending:int, refused:int},
     *   earnedBadges:list<array{code:string,title:string,subtitle:string,icon:string,tone:string,earned:bool,progress:string}>,
     *   nextBadge:array{code:string,title:string,subtitle:string,icon:string,tone:string,earned:bool,progress:string}|null
     * }
     */
    public function buildForDemandes(array $demandes): array
    {
        $total = count($demandes);
        $accepted = 0;
        $refused = 0;
        $pending = 0;
        $profileCompleteCount = 0;
        $strongMotivationCount = 0;
        $validPortfolioCount = 0;

        foreach ($demandes as $demande) {
            $etat = strtoupper((string) $demande->getEtat());
            if ($etat === 'ACCEPTEE') {
                $accepted++;
            } elseif ($etat === 'REFUSEE') {
                $refused++;
            } else {
                $pending++;
            }

            $role = trim((string) $demande->getRoleSouhaite());
            $dispo = trim((string) $demande->getDisponibilite());
            $motivation = trim((string) $demande->getMotivation());
            $portfolio = trim((string) $demande->getPortfolio());

            $isPortfolioValid = $portfolio !== '' && filter_var($portfolio, FILTER_VALIDATE_URL) !== false;
            if ($isPortfolioValid) {
                $validPortfolioCount++;
            }

            if (mb_strlen($motivation) >= 80) {
                $strongMotivationCount++;
            }

            if ($role !== '' && $dispo !== '' && $isPortfolioValid && mb_strlen($motivation) >= 40) {
                $profileCompleteCount++;
            }
        }

        $score = 20
            + ($accepted * 14)
            + ($pending * 3)
            + ($profileCompleteCount * 8)
            + ($strongMotivationCount * 4)
            + ($validPortfolioCount * 4)
            - ($refused * 2);
        $score = max(0, min(100, $score));

        $levelLabel = 'Niveau debutant';
        if ($score >= 80) {
            $levelLabel = 'Niveau expert';
        } elseif ($score >= 60) {
            $levelLabel = 'Niveau avance';
        } elseif ($score >= 40) {
            $levelLabel = 'Niveau intermediaire';
        }

        $badges = [
            [
                'code' => 'premier_pas',
                'title' => 'Premier pas',
                'subtitle' => 'Premiere candidature envoyee',
                'icon' => 'rocket',
                'tone' => 'blue',
                'earned' => $total >= 1,
                'progress' => $total . '/1',
            ],
            [
                'code' => 'profil_pro',
                'title' => 'Profil pro',
                'subtitle' => 'Role + dispo + portfolio + motivation solide',
                'icon' => 'shield',
                'tone' => 'purple',
                'earned' => $profileCompleteCount >= 1,
                'progress' => $profileCompleteCount . '/1',
            ],
            [
                'code' => 'talent_valide',
                'title' => 'Talent valide',
                'subtitle' => 'Au moins une demande acceptee',
                'icon' => 'check',
                'tone' => 'green',
                'earned' => $accepted >= 1,
                'progress' => $accepted . '/1',
            ],
            [
                'code' => 'candidat_regulier',
                'title' => 'Candidat regulier',
                'subtitle' => 'Quatre demandes ou plus',
                'icon' => 'layers',
                'tone' => 'gold',
                'earned' => $total >= 4,
                'progress' => $total . '/4',
            ],
            [
                'code' => 'resilient',
                'title' => 'Resilient',
                'subtitle' => 'Continue malgre un refus',
                'icon' => 'spark',
                'tone' => 'indigo',
                'earned' => $total >= 3 && $refused >= 1,
                'progress' => ((($total >= 3) ? 1 : 0) + (($refused >= 1) ? 1 : 0)) . '/2',
            ],
        ];

        $earnedBadges = array_values(array_filter($badges, static fn (array $badge): bool => $badge['earned'] === true));
        $nextBadge = null;
        foreach ($badges as $badge) {
            if (!$badge['earned']) {
                $nextBadge = $badge;
                break;
            }
        }

        return [
            'score' => $score,
            'levelLabel' => $levelLabel,
            'stats' => [
                'total' => $total,
                'accepted' => $accepted,
                'pending' => $pending,
                'refused' => $refused,
            ],
            'earnedBadges' => $earnedBadges,
            'nextBadge' => $nextBadge,
        ];
    }
}
