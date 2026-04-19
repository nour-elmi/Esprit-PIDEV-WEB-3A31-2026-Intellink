<?php

namespace App\Controller;

use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminForumController extends AbstractController
{
    #[Route('/admin/forum', name: 'app_admin_forum')]
    public function index(
        Request $request,
        PostRepository $postRepository,
        ReactionRepository $reactionRepository
    ): Response {
        $search = trim((string) $request->query->get('q', ''));
        $filter = $request->query->get('filter', 'Tous');
        $statusFilter = $request->query->get('status', 'Tous');

        $posts = $postRepository->findAdminFeed();

        if ($search !== '') {
            $posts = array_filter($posts, function ($post) use ($search) {
                return $post->getContent() && str_contains(
                    mb_strtolower($post->getContent()),
                    mb_strtolower($search)
                );
            });
        }

        if (in_array($statusFilter, ['Masques', 'Masqués', 'MasquÃ©s'], true)) {
            $posts = array_filter($posts, fn ($post) => $post->getStatus() === 'HIDDEN');
        } elseif ($statusFilter === 'Actifs') {
            $posts = array_filter($posts, fn ($post) => $post->getStatus() === 'ACTIVE');
        }

        if ($filter === '24h') {
            $limit = new \DateTimeImmutable('-24 hours');
            $posts = array_filter($posts, function ($post) use ($limit) {
                return $post->getCreatedAt() && $post->getCreatedAt() >= $limit;
            });
        }

        $postScores = [];
        foreach ($posts as $post) {
            $postScores[$post->getId()] = $reactionRepository->getScore($post->getId());
        }

        if ($filter === 'Populaire') {
            usort($posts, function ($a, $b) use ($postScores) {
                return ($postScores[$b->getId()] ?? 0) <=> ($postScores[$a->getId()] ?? 0);
            });
        }

        return $this->render('admin/forum/index.html.twig', [
            'posts' => $posts,
            'postScores' => $postScores,
            'search' => $search,
            'filter' => $filter,
            'statusFilter' => $statusFilter,
        ]);
    }

    #[Route('/admin/post/{id}/toggle-hide', name: 'app_admin_post_toggle_hide', methods: ['POST'])]
    public function toggleHide(
        int $id,
        PostRepository $postRepository,
        EntityManagerInterface $entityManager,
        MailService $mailService
    ): RedirectResponse {
        $post = $postRepository->find($id);

        if ($post) {
            $willHide = $post->getStatus() !== 'HIDDEN';
            $post->setStatus($willHide ? 'HIDDEN' : 'ACTIVE');
            $entityManager->flush();

            if ($willHide && $post->getAuthor() && $post->getAuthor()->getEmail()) {
                try {
                    $mailService->sendPostHiddenEmail($post->getAuthor(), $post);
                    $this->addFlash('success', 'Post masque et email envoye au proprietaire.');
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Post masque, mais email non envoye.');
                }
            } elseif ($willHide) {
                $this->addFlash('error', 'Post masque, mais aucun email utilisateur trouve.');
            }
        }

        return $this->redirectToRoute('app_admin_forum');
    }

    #[Route('/admin/post/{id}/toggle-lock', name: 'app_admin_post_toggle_lock', methods: ['POST'])]
    public function toggleLock(
        int $id,
        PostRepository $postRepository,
        EntityManagerInterface $entityManager,
        MailService $mailService
    ): RedirectResponse {
        $post = $postRepository->find($id);

        if ($post) {
            $willLock = !$post->isLocked();
            $post->setIsLocked($willLock);
            $entityManager->flush();

            $ownerEmail = $post->getAuthor()?->getEmail();

            if ($willLock && $ownerEmail) {
                try {
                    $mailService->sendPostLockedEmail($post->getAuthor(), $post);
                    $this->addFlash('success', 'Post verrouille et email envoye au proprietaire.');
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Post verrouille, mais email non envoye.');
                }
            } elseif ($willLock) {
                $this->addFlash('error', 'Post verrouille, mais aucun email proprietaire trouve.');
            }
        }

        return $this->redirectToRoute('app_admin_forum');
    }

    #[Route('/admin/post/{id}/toggle-pin', name: 'app_admin_post_toggle_pin', methods: ['POST'])]
    public function togglePin(
        int $id,
        PostRepository $postRepository,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $post = $postRepository->find($id);

        if ($post) {
            $post->setIsPinned(!$post->isPinned());
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_forum');
    }

    #[Route('/admin/post/{id}/delete', name: 'app_admin_post_delete', methods: ['POST'])]
    public function delete(
        int $id,
        PostRepository $postRepository,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $post = $postRepository->find($id);

        if ($post) {
            $entityManager->remove($post);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_forum');
    }
}
