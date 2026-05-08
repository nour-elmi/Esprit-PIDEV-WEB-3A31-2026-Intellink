<?php

namespace App\Controller;

use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminCommentsController extends AbstractController
{
    #[Route('/admin/post/{id}/comments', name: 'app_admin_comments')]
    public function index(
        int $id,
        Request $request,
        PostRepository $postRepository,
        CommentRepository $commentRepository
    ): Response {
        $post = $postRepository->find($id);

        if (!$post) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $search = trim((string) $request->query->get('q', ''));
        $filter = $request->query->get('filter', 'Tous');

        $comments = $commentRepository->findAdminByPost(
            $id,
            $search !== '' ? $search : null
        );

        if ($filter === 'Actifs') {
            $comments = array_filter($comments, fn($c) => strtoupper((string) $c->getStatus()) === 'ACTIVE');
        } elseif ($filter === 'Masqués') {
            $comments = array_filter($comments, fn($c) => strtoupper((string) $c->getStatus()) === 'HIDDEN');
        } elseif ($filter === 'Ancien') {
            usort($comments, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());
        } else {
            usort($comments, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        }

        return $this->render('admin/comments/index.html.twig', [
            'post' => $post,
            'comments' => $comments,
            'search' => $search,
            'filter' => $filter,
            'count' => count($comments),
        ]);
    }

    #[Route('/admin/comment/{id}/toggle-hide', name: 'app_admin_comment_toggle_hide', methods: ['POST'])]
    public function toggleHide(
        int $id,
        CommentRepository $commentRepository,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $comment = $commentRepository->find($id);

        if (!$comment || !$comment->getPost()) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $currentStatus = strtoupper((string) $comment->getStatus());
        $comment->setStatus($currentStatus === 'HIDDEN' ? 'ACTIVE' : 'HIDDEN');

        $entityManager->flush();

        return $this->redirectToRoute('app_admin_comments', [
            'id' => $comment->getPost()->getId(),
        ]);
    }
}