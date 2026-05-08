<?php

namespace App\Tests\Service;

use App\Entity\Collaboration;
use App\Service\ParticipationBadgeService;
use PHPUnit\Framework\TestCase;

class ParticipationBadgeServiceTest extends TestCase
{
    public function testBuildForDemandesComputesExpectedScoreAndLevel(): void
    {
        $service = new ParticipationBadgeService();

        $acceptedStrong = (new Collaboration())
            ->setEtat('ACCEPTEE')
            ->setRoleSouhaite('Backend')
            ->setDisponibilite('Temps plein')
            ->setMotivation(str_repeat('a', 100))
            ->setPortfolio('https://portfolio.dev');

        $pending = (new Collaboration())
            ->setEtat('EN_ATTENTE')
            ->setRoleSouhaite('Frontend')
            ->setDisponibilite('Part-time')
            ->setMotivation(str_repeat('b', 50))
            ->setPortfolio('https://github.com/user');

        $refusedNoPortfolio = (new Collaboration())
            ->setEtat('REFUSEE')
            ->setRoleSouhaite('QA')
            ->setDisponibilite('Weekend')
            ->setMotivation('Motivation courte')
            ->setPortfolio('not-a-valid-url');

        $result = $service->buildForDemandes([$acceptedStrong, $pending, $refusedNoPortfolio]);

        // score = 20 + (1*14) + (1*3) + (2*8) + (1*4) + (2*4) - (1*2) = 63
        self::assertSame(63, $result['score']);
        self::assertSame('Niveau avance', $result['levelLabel']);
        self::assertSame(3, $result['stats']['total']);
        self::assertSame(1, $result['stats']['accepted']);
        self::assertSame(1, $result['stats']['pending']);
        self::assertSame(1, $result['stats']['refused']);
    }

    public function testBuildForDemandesScoreIsClampedTo100(): void
    {
        $service = new ParticipationBadgeService();
        $demandes = [];

        for ($i = 0; $i < 20; $i++) {
            $demandes[] = (new Collaboration())
                ->setEtat('ACCEPTEE')
                ->setRoleSouhaite('Dev')
                ->setDisponibilite('Full')
                ->setMotivation(str_repeat('x', 120))
                ->setPortfolio('https://example.com/' . $i);
        }

        $result = $service->buildForDemandes($demandes);

        self::assertSame(100, $result['score']);
        self::assertSame('Niveau expert', $result['levelLabel']);
    }

    public function testBuildForDemandesWithNoDemandesReturnsBaseScoreAndZeroStats(): void
    {
        $service = new ParticipationBadgeService();

        $result = $service->buildForDemandes([]);

        self::assertSame(20, $result['score']);
        self::assertSame('Niveau debutant', $result['levelLabel']);
        self::assertSame(0, $result['stats']['total']);
        self::assertSame(0, $result['stats']['accepted']);
        self::assertSame(0, $result['stats']['pending']);
        self::assertSame(0, $result['stats']['refused']);
    }

    public function testBuildForDemandesInvalidPortfolioDoesNotAddPortfolioPoints(): void
    {
        $service = new ParticipationBadgeService();

        $demande = (new Collaboration())
            ->setEtat('ACCEPTEE')
            ->setRoleSouhaite('Backend')
            ->setDisponibilite('Temps plein')
            ->setMotivation(str_repeat('m', 100))
            ->setPortfolio('portfolio-invalide');

        $result = $service->buildForDemandes([$demande]);

        // score = 20 + 14 + 0 + 0 + 4 + 0 - 0 = 38 (pas de bonus portfolio, pas de profil complet)
        self::assertSame(38, $result['score']);
        self::assertSame('Niveau debutant', $result['levelLabel']);
    }
}
