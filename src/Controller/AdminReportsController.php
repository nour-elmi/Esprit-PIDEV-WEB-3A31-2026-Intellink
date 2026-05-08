<?php

namespace App\Controller;

use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminReportsController extends AbstractController
{
    #[Route('/admin/reports', name: 'app_admin_reports')]
    public function index(
        Request $request,
        ReportRepository $reportRepository
    ): Response {
        $search = trim((string) $request->query->get('q', ''));
        $filter = $request->query->get('filter', 'Tous (OPEN)');

        $reports = $reportRepository->findOpenReports();

        if ($filter === 'POST') {
            $reports = array_filter($reports, fn ($r) => strtoupper((string) $r->getType()) === 'POST');
        } elseif ($filter === 'COMMENT') {
            $reports = array_filter($reports, fn ($r) => strtoupper((string) $r->getType()) === 'COMMENT');
        }

        if ($search !== '') {
            $reports = array_filter($reports, function ($r) use ($search) {
                $q = mb_strtolower($search);

                return str_contains(mb_strtolower((string) $r->getReason()), $q)
                    || str_contains(mb_strtolower((string) $r->getType()), $q)
                    || str_contains((string) $r->getTargetId(), $search)
                    || str_contains((string) $r->getReporterId(), $search);
            });
        }

        return $this->render('admin/reports/index.html.twig', [
            'reports' => $reports,
            'filter' => $filter,
            'search' => $search,
            'count' => count($reports),
        ]);
    }

    #[Route('/admin/report/{id}/accept', name: 'app_admin_report_accept', methods: ['POST'])]
    public function accept(
        int $id,
        ReportRepository $reportRepository,
        PostRepository $postRepository,
        CommentRepository $commentRepository,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $report = $reportRepository->find($id);

        if (!$report) {
            return $this->redirectToRoute('app_admin_reports');
        }

        if (strtoupper((string) $report->getType()) === 'POST') {
            $post = $postRepository->find($report->getTargetId());
            if ($post) {
                $post->setStatus('HIDDEN');
            }
        } elseif (strtoupper((string) $report->getType()) === 'COMMENT') {
            $comment = $commentRepository->find($report->getTargetId());
            if ($comment) {
                $comment->setStatus('HIDDEN');
            }
        }

        $report->setStatus('ACCEPTED');
        $report->setHandledBy(1);
        $report->setHandledAt(new \DateTimeImmutable());

        $entityManager->flush();

        return $this->redirectToRoute('app_admin_reports');
    }

    #[Route('/admin/report/{id}/reject', name: 'app_admin_report_reject', methods: ['POST'])]
    public function reject(
        int $id,
        ReportRepository $reportRepository,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $report = $reportRepository->find($id);

        if ($report) {
            $report->setStatus('REJECTED');
            $report->setHandledBy(1);
            $report->setHandledAt(new \DateTimeImmutable());

            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_reports');
    }
}