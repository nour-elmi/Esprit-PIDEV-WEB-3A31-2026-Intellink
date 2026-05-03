<?php

namespace App\Controller;

use App\Controller\Concern\ResolvesForumUser;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ForumController extends AbstractController
{
    use ResolvesForumUser;

    #[Route('/forum', name: 'app_forum')]
    public function index(
        Request $request,
        PostRepository $postRepository,
        ReactionRepository $reactionRepository,
        UserRepository $userRepository
    ): Response {
        $utilisateur = $this->getForumUser($userRepository);
        $currentUserId = $utilisateur?->getId();

        $search = trim((string) $request->query->get('q', ''));
        $filter = (string) $request->query->get('filter', 'accueil');
        $sort = (string) $request->query->get('sort', 'Nouveau');

        $posts = $search !== ''
            ? $postRepository->searchActivePosts($search)
            : $postRepository->findNewestActiveOnly();

        if ($filter === 'actualites') {
            $limit = new \DateTimeImmutable('-24 hours');

            $posts = array_filter($posts, function ($post) use ($limit) {
                return $post->getCreatedAt() >= $limit;
            });
        }

        $postScores = $this->buildPostScores($posts, $reactionRepository);

        if ($filter === 'populaire' || $sort === 'Populaire') {
            usort($posts, function ($a, $b) use ($postScores) {
                return ($postScores[$b->getId()] ?? 0) <=> ($postScores[$a->getId()] ?? 0);
            });
        }

        return $this->render('forum/index.html.twig', [
            'posts' => $posts,
            'postScores' => $postScores,
            'search' => $search,
            'filter' => $filter,
            'sort' => $sort,
            'currentUserId' => $currentUserId,
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/forum/search', name: 'app_forum_ajax_search', methods: ['GET'])]
    public function ajaxSearch(
        Request $request,
        PostRepository $postRepository,
        ReactionRepository $reactionRepository
    ): JsonResponse {
        $search = trim((string) $request->query->get('q', ''));
        $filter = (string) $request->query->get('filter', 'accueil');
        $sort = (string) $request->query->get('sort', 'Nouveau');

        $posts = $search !== ''
            ? $postRepository->searchActivePosts($search)
            : $postRepository->findNewestActiveOnly();

        if ($filter === 'actualites') {
            $limit = new \DateTimeImmutable('-24 hours');

            $posts = array_filter($posts, function ($post) use ($limit) {
                return $post->getCreatedAt() >= $limit;
            });
        }

        $postScores = $this->buildPostScores($posts, $reactionRepository);

        if ($filter === 'populaire' || $sort === 'Populaire') {
            usort($posts, function ($a, $b) use ($postScores) {
                return ($postScores[$b->getId()] ?? 0) <=> ($postScores[$a->getId()] ?? 0);
            });
        }

        $data = [];

        foreach ($posts as $post) {
            $images = [];

            foreach ($post->getImages() as $image) {
                if ($image->getImageUrl() !== null) {
                    $images[] = '/uploads/posts/' . $image->getImageUrl();
                }
            }

            $data[] = [
                'id' => $post->getId(),
                'content' => $post->getContent(),
                'author' => $post->getAuthor()?->getNom() ?? 'Unknown',
                'createdAt' => $post->getCreatedAt()->format('d/m/Y H:i'),
                'isEdited' => $post->isEdited(),
                'isPinned' => $post->isPinned(),
                'isLocked' => $post->isLocked(),
                'score' => $postScores[$post->getId()] ?? 0,
                'images' => $images,
            ];
        }

        return $this->json([
            'success' => true,
            'posts' => $data,
        ]);
    }

    private function buildPostScores(array $posts, ReactionRepository $reactionRepository): array
    {
        $postScores = [];

        foreach ($posts as $post) {
            $postScores[$post->getId()] = $reactionRepository->getScore($post->getId());
        }

        return $postScores;
    }
}