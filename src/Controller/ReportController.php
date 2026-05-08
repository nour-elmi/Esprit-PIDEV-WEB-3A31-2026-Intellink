<?php

namespace App\Controller;

use App\Controller\Concern\ResolvesForumUser;
use App\Entity\Report;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ReportController extends AbstractController
{
    use ResolvesForumUser;

    #[Route('/report/create', name: 'app_report_create', methods: ['POST'])]
    public function create(
        Request $request,
        ReportRepository $reportRepository,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository
    ): RedirectResponse {
        $type = trim((string) $request->request->get('targetType', ''));
        $targetId = (int) $request->request->get('targetId', 0);
        $reason = trim((string) $request->request->get('reason', ''));
        $utilisateur = $this->getForumUser($userRepository);
        $reporterId = $utilisateur?->getId();

        if (!$reporterId) {
            $this->addFlash('error', 'Vous devez etre connecte pour signaler un contenu.');
            return $this->redirectToRoute('app_login');
        }

        if ($type === '' || $targetId <= 0 || $reason === '') {
            $this->addFlash('error', 'Données du signalement invalides.');
            return $this->redirectToRoute('app_forum');
        }

        if (!in_array($type, ['POST', 'COMMENT'])) {
            $this->addFlash('error', 'Type de signalement invalide.');
            return $this->redirectToRoute('app_forum');
        }

        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 200) {
            $this->addFlash('error', 'La raison doit contenir entre 5 et 200 caractères.');
            return $this->redirectToRoute('app_forum');
        }

        if ($reportRepository->hasOpenReport($type, $targetId, $reporterId)) {
            $this->addFlash('error', 'Vous avez déjà signalé ce contenu.');
            return $this->redirectToRoute('app_forum');
        }

        $report = new Report();
        $report->setType($type);
        $report->setTargetId($targetId);
        $report->setReporterId($reporterId);
        $report->setReason($reason);
        $report->setStatus('OPEN');

        $entityManager->persist($report);
        $entityManager->flush();

        $this->addFlash('success', 'Signalement envoyé avec succès.');
        return $this->redirectToRoute('app_forum');
    }
}
