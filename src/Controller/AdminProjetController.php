<?php

namespace App\Controller;

use App\ExportBundle\Service\AdminProjectCsvExporter;
use App\Entity\Projet;
use App\Entity\Collaboration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AdminProjetController extends AbstractController
{
    public function index(EntityManagerInterface $entityManager): Response
    {
        $projets = $entityManager->getRepository(Projet::class)->findBy([], [
            'id' => 'DESC'
        ]);
        $collaborations = $entityManager->getRepository(Collaboration::class)->findAll();

        $projetsAvecStats = [];
        $participantsByProjetId = [];

        foreach ($collaborations as $collaboration) {
            $projet = $collaboration->getProjet();
            $projetId = $projet?->getId();
            if ($projetId === null) {
                continue;
            }
            $participantsByProjetId[$projetId] = ($participantsByProjetId[$projetId] ?? 0) + 1;
        }

        foreach ($projets as $projet) {
            $nbParticipations = (int) ($participantsByProjetId[$projet->getId()] ?? 0);

            $projetsAvecStats[] = [
                'projet' => $projet,
                'nbParticipations' => $nbParticipations,
            ];
        }

        $statusCounts = [
            'En cours' => 0,
            'En attente' => 0,
            'Terminé' => 0,
            'Autre' => 0,
        ];
        $participationStateCounts = [
            'EN_ATTENTE' => 0,
            'ACCEPTEE' => 0,
            'REFUSEE' => 0,
            'AUTRE' => 0,
        ];
        $creatorCounts = [];
        $totalBudget = 0.0;
        $activeProjects = 0;
        $monthlyMap = [];

        $now = new \DateTimeImmutable('first day of this month');
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->modify('-' . $i . ' month');
            $key = $month->format('Y-m');
            $monthlyMap[$key] = 0;
        }

        foreach ($projets as $projet) {
            $statusRaw = strtolower((string) $projet->getStatut());
            if (str_contains($statusRaw, 'cours')) {
                $statusCounts['En cours']++;
                $activeProjects++;
            } elseif (str_contains($statusRaw, 'attente')) {
                $statusCounts['En attente']++;
            } elseif (str_contains($statusRaw, 'term')) {
                $statusCounts['Terminé']++;
            } else {
                $statusCounts['Autre']++;
            }

            $totalBudget += (float) ($projet->getPrix() ?? 0);
            $creator = trim((string) $projet->getCreateur());
            $creatorKey = $creator !== '' ? $creator : 'Inconnu';
            $creatorCounts[$creatorKey] = ($creatorCounts[$creatorKey] ?? 0) + 1;

            $dateCreation = $projet->getDateCreation();
            if ($dateCreation instanceof \DateTimeInterface) {
                $monthKey = $dateCreation->format('Y-m');
                if (array_key_exists($monthKey, $monthlyMap)) {
                    $monthlyMap[$monthKey]++;
                }
            }
        }

        foreach ($collaborations as $collaboration) {
            $etat = strtoupper((string) $collaboration->getEtat());
            if ($etat === 'EN_ATTENTE' || $etat === 'ACCEPTEE' || $etat === 'REFUSEE') {
                $participationStateCounts[$etat]++;
            } else {
                $participationStateCounts['AUTRE']++;
            }
        }

        arsort($creatorCounts);
        $topCreators = [];
        foreach (array_slice($creatorCounts, 0, 5, true) as $name => $count) {
            $topCreators[] = [
                'name' => $name,
                'count' => (int) $count,
            ];
        }

        $topProjects = [];
        foreach ($projetsAvecStats as $item) {
            $topProjects[] = [
                'title' => (string) $item['projet']->getTitre(),
                'participants' => (int) $item['nbParticipations'],
            ];
        }
        usort($topProjects, static fn (array $a, array $b): int => $b['participants'] <=> $a['participants']);
        $topProjects = array_slice($topProjects, 0, 5);

        $monthNames = [
            '01' => 'Jan', '02' => 'Fév', '03' => 'Mar', '04' => 'Avr',
            '05' => 'Mai', '06' => 'Juin', '07' => 'Juil', '08' => 'Aoû',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Déc',
        ];
        $monthlyLabels = [];
        $monthlyProjectCounts = [];
        foreach ($monthlyMap as $key => $count) {
            [$year, $month] = explode('-', $key);
            $monthlyLabels[] = ($monthNames[$month] ?? $month) . ' ' . $year;
            $monthlyProjectCounts[] = (int) $count;
        }

        $totalProjects = count($projets);
        $totalParticipations = count($collaborations);
        $averageParticipations = $totalProjects > 0 ? round($totalParticipations / $totalProjects, 1) : 0;
        $averageBudget = $totalProjects > 0 ? round($totalBudget / $totalProjects, 2) : 0;
        $activeRate = $totalProjects > 0 ? (int) round(($activeProjects / $totalProjects) * 100) : 0;

        $adminStats = [
            'totalProjects' => $totalProjects,
            'totalParticipations' => $totalParticipations,
            'averageParticipations' => $averageParticipations,
            'totalBudget' => round($totalBudget, 2),
            'averageBudget' => $averageBudget,
            'activeProjects' => $activeProjects,
            'activeRate' => $activeRate,
            'statusCounts' => $statusCounts,
            'participationStateCounts' => $participationStateCounts,
            'monthlyLabels' => $monthlyLabels,
            'monthlyProjectCounts' => $monthlyProjectCounts,
            'topCreators' => $topCreators,
            'topProjects' => $topProjects,
        ];

        return $this->render('admin/projets.html.twig', [
            'projetsAvecStats' => $projetsAvecStats,
            'adminStats' => $adminStats,
        ]);
    }

    public function participations(int $id, EntityManagerInterface $entityManager): Response
    {
        $projet = $entityManager->getRepository(Projet::class)->find($id);

        if (!$projet) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $participations = $entityManager->getRepository(Collaboration::class)->findBy(
            ['projet' => $projet],
            ['id' => 'DESC']
        );

        return $this->render('admin/participations.html.twig', [
            'projet' => $projet,
            'participations' => $participations,
        ]);
    }

    public function delete(int $id, EntityManagerInterface $entityManager): RedirectResponse
    {
        $projet = $entityManager->getRepository(Projet::class)->find($id);

        if (!$projet) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $entityManager->remove($projet);
        $entityManager->flush();

        $this->addFlash('success', 'Projet supprimé avec succès.');

        return $this->redirectToRoute('admin_projets');
    }

    public function exportCsv(AdminProjectCsvExporter $csvExporter): Response
    {
        return $csvExporter->exportProjectsCsv();
    }
}
