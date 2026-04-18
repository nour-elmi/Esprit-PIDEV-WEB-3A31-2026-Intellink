<?php

namespace App\ExportBundle\Service;

use App\Entity\Collaboration;
use App\Entity\Projet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminProjectCsvExporter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function exportProjectsCsv(): StreamedResponse
    {
        $projects = $this->entityManager->getRepository(Projet::class)->findBy([], ['id' => 'DESC']);
        $collaborations = $this->entityManager->getRepository(Collaboration::class)->findAll();

        $participantsByProjectId = [];
        $acceptedByProjectId = [];
        foreach ($collaborations as $collaboration) {
            $project = $collaboration->getProjet();
            $projectId = $project?->getId();
            if ($projectId === null) {
                continue;
            }

            $participantsByProjectId[$projectId] = ($participantsByProjectId[$projectId] ?? 0) + 1;

            $etat = strtoupper((string) ($collaboration->getEtat() ?? ''));
            if ($etat === 'ACCEPTEE') {
                $acceptedByProjectId[$projectId] = ($acceptedByProjectId[$projectId] ?? 0) + 1;
            }
        }

        $filename = sprintf('admin-projets-%s.csv', (new \DateTimeImmutable())->format('Ymd-His'));
        $response = new StreamedResponse(function () use ($projects, $participantsByProjectId, $acceptedByProjectId): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for better compatibility with Excel.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID',
                'Titre',
                'Description',
                'Createur',
                'Statut',
                'Prix',
                'ParticipantsTotal',
                'ParticipantsAcceptes',
                'DateCreation',
            ], ';');

            foreach ($projects as $project) {
                $projectId = (int) ($project->getId() ?? 0);
                $description = trim((string) ($project->getDescription() ?? ''));
                $description = preg_replace('/\s+/', ' ', $description) ?? $description;

                fputcsv($handle, [
                    $projectId,
                    (string) $project->getTitre(),
                    $description,
                    (string) $project->getCreateur(),
                    (string) $project->getStatut(),
                    (float) ($project->getPrix() ?? 0),
                    (int) ($participantsByProjectId[$projectId] ?? 0),
                    (int) ($acceptedByProjectId[$projectId] ?? 0),
                    $project->getDateCreation()?->format('Y-m-d H:i:s') ?? '',
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}

