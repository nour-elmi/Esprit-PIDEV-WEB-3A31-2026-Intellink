<?php

namespace App\Controller;

use App\Controller\Concern\ResolvesForumUser;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        $filter = $request->query->get('filter', 'accueil');
        $sort = $request->query->get('sort', 'Nouveau');

        $posts = $postRepository->findNewestActiveOnly();

        if ($search !== '') {
            $posts = array_filter($posts, function ($post) use ($search) {
                return $post->getContent() && str_contains(
                    mb_strtolower($post->getContent()),
                    mb_strtolower($search)
                );
            });
        }

        if ($filter === 'actualites') {
            $limit = new \DateTimeImmutable('-24 hours');
            $posts = array_filter($posts, function ($post) use ($limit) {
                return $post->getCreatedAt() && $post->getCreatedAt() >= $limit;
            });
        }

        $postScores = [];
        foreach ($posts as $post) {
            $postId = $post->getId();
            if ($postId === null) {
                continue;
            }
            $postScores[$postId] = $reactionRepository->getScore($postId);
        }

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
}
