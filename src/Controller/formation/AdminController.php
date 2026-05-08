<?php

namespace App\Controller\formation;

use App\Repository\FormationRepository;
use App\Repository\ParticipationRepository;
use App\Repository\ProgressionFormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/formation/admin')]

final class AdminController extends AbstractController
{
    #[Route('/formations', name: 'app_admin_formations')]
    public function formations(
        FormationRepository $repo,
        ParticipationRepository $participationRepository,
        ProgressionFormationRepository $progressionRepository
    ): Response
    {
        $formations = $repo->findAll();
        $participations = $participationRepository->findAll();
        $progressions = $progressionRepository->findAll();

        $totalFormations = count($formations);
        $totalParticipations = count($participations);
        $avgInscriptions = $totalFormations > 0 ? round($totalParticipations / $totalFormations, 2) : 0.0;

        $uniqueLearners = [];
        $monthly = [];
        $monthOrder = [];
        $now = new \DateTimeImmutable('first day of this month');
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->modify("-{$i} months");
            $key = $month->format('Y-m');
            $monthOrder[] = $key;
            $monthly[$key] = 0;
        }

        foreach ($participations as $participation) {
            $uid = $participation->getIdUtilisateur();
            if ($uid !== null) {
                $uniqueLearners[(string) $uid] = true;
            }

            $date = $participation->getDateInscription();
            if ($date instanceof \DateTimeInterface) {
                $key = $date->format('Y-m');
                if (array_key_exists($key, $monthly)) {
                    $monthly[$key]++;
                }
            }
        }

        $totalProgression = count($progressions);
        $progressionSum = 0.0;
        $completedCount = 0;
        $statusCounts = [
            'non_commence' => 0,
            'en_cours' => 0,
            'termine' => 0,
        ];

        foreach ($progressions as $progression) {
            $progressionSum += (float) $progression->getPourcentage();
            $status = $progression->getStatut();
            if (!isset($statusCounts[$status])) {
                $statusCounts[$status] = 0;
            }
            $statusCounts[$status]++;
            if ($status === 'termine') {
                $completedCount++;
            }
        }

        $avgProgression = $totalProgression > 0 ? round($progressionSum / $totalProgression, 2) : 0.0;
        $completionRate = $totalProgression > 0 ? round(($completedCount / $totalProgression) * 100, 2) : 0.0;

        $domainCounts = [];
        $levelCounts = [];
        $topFormation = [];
        foreach ($formations as $formation) {
            $domain = trim((string) ($formation->getDomaine() ?? ''));
            if ($domain === '') {
                $domain = 'Non specifie';
            }
            $domainCounts[$domain] = ($domainCounts[$domain] ?? 0) + 1;

            $level = trim((string) ($formation->getNiveau() ?? ''));
            if ($level === '') {
                $level = 'Non specifie';
            }
            $levelCounts[$level] = ($levelCounts[$level] ?? 0) + 1;

            $topFormation[(string) $formation->getTitre()] = count($formation->getParticipations());
        }

        arsort($topFormation);
        $topFormation = array_slice($topFormation, 0, 8, true);

        $monthlyLabels = [];
        $monthlyData = [];
        foreach ($monthOrder as $monthKey) {
            $date = \DateTimeImmutable::createFromFormat('Y-m', $monthKey);
            $monthlyLabels[] = $date ? $date->format('M Y') : $monthKey;
            $monthlyData[] = (int) ($monthly[$monthKey] ?? 0);
        }

        $stats = [
            'kpis' => [
                'totalFormations' => $totalFormations,
                'totalParticipations' => $totalParticipations,
                'activeLearners' => count($uniqueLearners),
                'avgInscriptions' => $avgInscriptions,
                'avgProgression' => $avgProgression,
                'completionRate' => $completionRate,
            ],
            'domain' => [
                'labels' => array_keys($domainCounts),
                'data' => array_values($domainCounts),
            ],
            'level' => [
                'labels' => array_keys($levelCounts),
                'data' => array_values($levelCounts),
            ],
            'monthly' => [
                'labels' => $monthlyLabels,
                'data' => $monthlyData,
            ],
            'topFormations' => [
                'labels' => array_keys($topFormation),
                'data' => array_values($topFormation),
            ],
            'status' => [
                'labels' => ['Non commence', 'En cours', 'Termine'],
                'data' => [
                    (int) ($statusCounts['non_commence'] ?? 0),
                    (int) ($statusCounts['en_cours'] ?? 0),
                    (int) ($statusCounts['termine'] ?? 0),
                ],
            ],
        ];

        return $this->render('formation/admin/formations.html.twig', [
            'formations' => $formations,
            'stats' => $stats,
        ]);
    }

    #[Route('/participations/{id}', name: 'app_admin_participations')]
    public function participations(int $id, FormationRepository $formationRepo, ParticipationRepository $participationRepo): Response
    {
        $formation = $formationRepo->find($id);

        if (!$formation) {
            throw $this->createNotFoundException('Formation introuvable');
        }

        return $this->render('formation/admin/participations.html.twig', [
            'formation' => $formation,
            'participations' => $participationRepo->findBy(['formation' => $formation]),
        ]);
    }

    #[Route('/participation/delete/{id}', name: 'app_admin_participation_delete')]
    public function deleteParticipation(int $id, ParticipationRepository $repo, FormationRepository $formationRepo, EntityManagerInterface $em): Response
    {
        $participation = $repo->find($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation introuvable');
        }

        $formation = $participation->getFormation(); // CHANGEMENT
        if (!$formation) { // CHANGEMENT: securise appel sur relation nullable
            throw $this->createNotFoundException('Formation introuvable pour cette participation.');
        }
        $formationId = $formation->getIdFormation();

        $em->remove($participation);
        $em->flush();

        $this->addFlash('success', 'Participation supprimée avec succès !');
        return $this->redirectToRoute('app_admin_participations', ['id' => $formationId]);
    }

    
}
