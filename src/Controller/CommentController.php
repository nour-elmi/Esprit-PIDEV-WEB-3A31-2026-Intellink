<?php

namespace App\Controller;

use App\Controller\Concern\ResolvesForumUser;
use App\Entity\Comment;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommentController extends AbstractController
{
    use ResolvesForumUser;

    #[Route('/post/{id}/comments', name: 'app_comments')]
    public function index(
        int $id,
        Request $request,
        PostRepository $postRepository,
        CommentRepository $commentRepository,
        UserRepository $userRepository
    ): Response {
        $post = $postRepository->find($id);
        $utilisateur = $this->getForumUser($userRepository);

        if (!$post) {
            throw $this->createNotFoundException('Post introuvable.');
        }

        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'Nouveau');

        [$rootComments, $groupedReplies] = $this->getThreadData(
            $id,
            $sort,
            $search,
            $commentRepository
        );

        return $this->render('comment/index.html.twig', [
            'post' => $post,
            'rootComments' => $rootComments,
            'groupedReplies' => $groupedReplies,
            'search' => $search,
            'sort' => $sort,
            'currentUserId' => $utilisateur?->getId(),
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/post/{id}/comments/ajax', name: 'app_comments_ajax', methods: ['GET'])]
    public function ajaxList(
        int $id,
        Request $request,
        PostRepository $postRepository,
        CommentRepository $commentRepository,
        UserRepository $userRepository
    ): JsonResponse {
        $post = $postRepository->find($id);
        $utilisateur = $this->getForumUser($userRepository);

        if (!$post) {
            return $this->json([
                'success' => false,
                'message' => 'Post introuvable.',
            ], 404);
        }

        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'Nouveau');

        [$rootComments, $groupedReplies] = $this->getThreadData(
            $id,
            $sort,
            $search,
            $commentRepository
        );

        $html = '';

        foreach ($rootComments as $comment) {
            $html .= $this->renderView('comment/_comment.html.twig', [
                'comment' => $comment,
                'groupedReplies' => $groupedReplies,
                'depth' => 0,
                'currentUserId' => $utilisateur?->getId(),
                'post' => $post,
            ]);
        }

        if ($html === '') {
            $html = '<div class="post"><p class="muted">Aucun commentaire trouvé.</p></div>';
        }

        return $this->json([
            'success' => true,
            'html' => $html,
        ]);
    }

    #[Route('/post/{id}/comments/add', name: 'app_comment_add', methods: ['POST'])]
    public function add(
        int $id,
        Request $request,
        PostRepository $postRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $post = $postRepository->find($id);
        $author = $this->getForumUser($userRepository);

        if (!$post || !$author) {
            return $this->ajaxOrRedirect($request, false, 'Vous devez etre connecte pour commenter.', 'app_login');
        }

        if ($post->isLocked()) {
            return $this->ajaxOrRedirect($request, false, 'Ce post est verrouillé.', 'app_comments', ['id' => $id]);
        }

        $content = trim((string) $request->request->get('content', ''));

        if ($content === '' || mb_strlen($content) < 2 || mb_strlen($content) > 300) {
            return $this->ajaxOrRedirect($request, false, 'Le commentaire doit contenir entre 2 et 300 caractères.', 'app_comments', ['id' => $id]);
        }

        $comment = new Comment();
        $comment->setPost($post);
        $comment->setAuthor($author);
        $comment->setContent($content);
        $comment->setStatus('ACTIVE');
        $comment->setIsEdited(false);

        $entityManager->persist($comment);
        $entityManager->flush();

        return $this->ajaxOrRedirect($request, true, 'Commentaire ajouté.', 'app_comments', ['id' => $id]);
    }

    #[Route('/comment/{id}/reply', name: 'app_comment_reply', methods: ['POST'])]
    public function reply(
        int $id,
        Request $request,
        CommentRepository $commentRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $parentComment = $commentRepository->find($id);
        $author = $this->getForumUser($userRepository);

        if (!$parentComment || !$author || !$parentComment->getPost()) {
            return $this->ajaxOrRedirect($request, false, 'Vous devez etre connecte pour repondre.', 'app_login');
        }

        $post = $parentComment->getPost();

        if ($post->isLocked()) {
            return $this->ajaxOrRedirect($request, false, 'Ce post est verrouillé.', 'app_comments', ['id' => $post->getId()]);
        }

        $content = trim((string) $request->request->get('content', ''));

        if ($content === '' || mb_strlen($content) < 2 || mb_strlen($content) > 300) {
            return $this->ajaxOrRedirect($request, false, 'La réponse doit contenir entre 2 et 300 caractères.', 'app_comments', ['id' => $post->getId()]);
        }

        $reply = new Comment();
        $reply->setPost($post);
        $reply->setAuthor($author);
        $reply->setParent($parentComment);
        $reply->setContent($content);
        $reply->setStatus('ACTIVE');
        $reply->setIsEdited(false);

        $entityManager->persist($reply);
        $entityManager->flush();

        return $this->ajaxOrRedirect($request, true, 'Réponse ajoutée.', 'app_comments', ['id' => $post->getId()]);
    }

    #[Route('/comment/{id}/edit', name: 'app_comment_edit', methods: ['POST'])]
    public function edit(
        int $id,
        Request $request,
        CommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository
    ): Response {
        $utilisateur = $this->getForumUser($userRepository);
        $comment = $commentRepository->find($id);

        if (!$comment || !$comment->getPost()) {
            return $this->ajaxOrRedirect($request, false, 'Commentaire introuvable.', 'app_forum');
        }

        $postId = $comment->getPost()->getId();

        if (!$utilisateur || !$comment->getAuthor() || $comment->getAuthor()->getId() !== $utilisateur->getId()) {
            return $this->ajaxOrRedirect($request, false, 'Action non autorisée.', 'app_comments', ['id' => $postId]);
        }

        if ($comment->getPost()->isLocked()) {
            return $this->ajaxOrRedirect($request, false, 'Ce post est verrouillé.', 'app_comments', ['id' => $postId]);
        }

        $content = trim((string) $request->request->get('content', ''));

        if ($content === '' || mb_strlen($content) < 2 || mb_strlen($content) > 300) {
            return $this->ajaxOrRedirect($request, false, 'Le commentaire doit contenir entre 2 et 300 caractères.', 'app_comments', ['id' => $postId]);
        }

        $comment->setContent($content);
        $comment->setIsEdited(true);
        $comment->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->flush();

        return $this->ajaxOrRedirect($request, true, 'Commentaire modifié.', 'app_comments', ['id' => $postId]);
    }

    #[Route('/comment/{id}/delete', name: 'app_comment_delete', methods: ['POST'])]
    public function delete(
        int $id,
        CommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        Request $request
    ): Response {
        $utilisateur = $this->getForumUser($userRepository);
        $comment = $commentRepository->find($id);

        if (!$comment || !$comment->getPost()) {
            return $this->ajaxOrRedirect($request, false, 'Commentaire introuvable.', 'app_forum');
        }

        $postId = $comment->getPost()->getId();

        if (!$utilisateur || !$comment->getAuthor() || $comment->getAuthor()->getId() !== $utilisateur->getId()) {
            return $this->ajaxOrRedirect($request, false, 'Action non autorisée.', 'app_comments', ['id' => $postId]);
        }

        if ($comment->getPost()->isLocked()) {
            return $this->ajaxOrRedirect($request, false, 'Ce post est verrouillé.', 'app_comments', ['id' => $postId]);
        }

        $comment->setStatus('DELETED');
        $entityManager->flush();

        return $this->ajaxOrRedirect($request, true, 'Commentaire supprimé.', 'app_comments', ['id' => $postId]);
    }

    private function getThreadData(
        int $postId,
        string $sort,
        string $search,
        CommentRepository $commentRepository
    ): array {
        $comments = $commentRepository->findThreadByPost(
            $postId,
            $sort === 'Ancien' ? 'ASC' : 'DESC',
            $search !== '' ? $search : null
        );

        $groupedReplies = [];
        $rootComments = [];

        foreach ($comments as $comment) {
            if ($comment->getParent()) {
                $groupedReplies[$comment->getParent()->getId()][] = $comment;
            } else {
                $rootComments[] = $comment;
            }
        }

        return [$rootComments, $groupedReplies];
    }

    private function ajaxOrRedirect(
        Request $request,
        bool $success,
        string $message,
        string $route,
        array $params = []
    ): Response {
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => $success,
                'message' => $message,
            ], $success ? 200 : 400);
        }

        $this->addFlash($success ? 'success' : 'error', $message);

        return $this->redirectToRoute($route, $params);
    }
}