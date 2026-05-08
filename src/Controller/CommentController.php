<?php

namespace App\Controller;

use App\Controller\Concern\ResolvesForumUser;
use App\Entity\Comment;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

        // CHANGEMENT: ajout d'un guard pour eviter getId() sur Post|null.
        // Ancien code: pas de verification explicite de $post null.
        if (!$post) {
            throw $this->createNotFoundException('Post introuvable.');
        }

        $search = trim((string) $request->query->get('q', ''));
        $sort = $request->query->get('sort', 'Nouveau');

        $comments = $commentRepository->findThreadByPost(
            $id,
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

    #[Route('/post/{id}/comments/add', name: 'app_comment_add', methods: ['POST'])]
    public function add(
        int $id,
        Request $request,
        PostRepository $postRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $post = $postRepository->find($id);
        $author = $this->getForumUser($userRepository);

        if (!$post || !$author) {
            $this->addFlash('error', 'Vous devez etre connecte pour commenter.');
            return $this->redirectToRoute('app_login');
        }

        // CHANGEMENT: $post est non-null apres le guard.
        // Ancien code: if ($post && $post->isLocked()) {
        if ($post->isLocked()) {
            $this->addFlash('error', 'Ce post est verrouillé.');
            return $this->redirectToRoute('app_comments', ['id' => $id]);
        }

        $content = trim((string) $request->request->get('content', ''));

        if ($content === '' || mb_strlen($content) < 2 || mb_strlen($content) > 300) {
            $this->addFlash('error', 'Le commentaire doit contenir entre 2 et 300 caractères.');
            return $this->redirectToRoute('app_comments', ['id' => $id]);
        }

        $comment = new Comment();
        $comment->setPost($post);
        $comment->setAuthor($author);
        $comment->setContent($content);
        $comment->setStatus('ACTIVE');
        $comment->setIsEdited(false);

        $entityManager->persist($comment);
        $entityManager->flush();

        return $this->redirectToRoute('app_comments', ['id' => $id]);
    }

    #[Route('/comment/{id}/reply', name: 'app_comment_reply', methods: ['POST'])]
    public function reply(
        int $id,
        Request $request,
        CommentRepository $commentRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $parentComment = $commentRepository->find($id);
        $author = $this->getForumUser($userRepository);

        if (!$parentComment || !$author) {
            $this->addFlash('error', 'Vous devez etre connecte pour repondre.');
            return $this->redirectToRoute('app_login');
        }

        $post = $parentComment->getPost();

        // CHANGEMENT: ajout d'un guard pour eviter getId() sur Post|null.
        // Ancien code: pas de verification explicite de $post null.
        if (!$post) {
            return $this->redirectToRoute('app_forum');
        }

        // CHANGEMENT: $post est non-null apres le guard.
        // Ancien code: if ($post && $post->isLocked()) {
        if ($post->isLocked()) {
            $this->addFlash('error', 'Ce post est verrouillé.');
            return $this->redirectToRoute('app_comments', ['id' => $post->getId()]);
        }

        $content = trim((string) $request->request->get('content', ''));

        if ($content === '' || mb_strlen($content) < 2 || mb_strlen($content) > 300) {
            $this->addFlash('error', 'La réponse doit contenir entre 2 et 300 caractères.');
            return $this->redirectToRoute('app_comments', ['id' => $post->getId()]);
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

        return $this->redirectToRoute('app_comments', ['id' => $post->getId()]);
    }

    #[Route('/comment/{id}/edit', name: 'app_comment_edit', methods: ['POST'])]
    public function edit(
        int $id,
        Request $request,
        CommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository
    ): RedirectResponse {
        $utilisateur = $this->getForumUser($userRepository);
        $comment = $commentRepository->find($id);

        if (!$comment || !$comment->getPost()) {
            return $this->redirectToRoute('app_forum');
        }

        if (!$utilisateur || !$comment->getAuthor() || $comment->getAuthor()->getId() !== $utilisateur->getId()) {
            return $this->redirectToRoute('app_comments', ['id' => $comment->getPost()->getId()]);
        }

        if ($comment->getPost()->isLocked()) {
            $this->addFlash('error', 'Ce post est verrouillé.');
            return $this->redirectToRoute('app_comments', ['id' => $comment->getPost()->getId()]);
        }

        $content = trim((string) $request->request->get('content', ''));

        if ($content === '' || mb_strlen($content) < 2 || mb_strlen($content) > 300) {
            $this->addFlash('error', 'Le commentaire doit contenir entre 2 et 300 caractères.');
            return $this->redirectToRoute('app_comments', ['id' => $comment->getPost()->getId()]);
        }

        $comment->setContent($content);
        $comment->setIsEdited(true);
        $comment->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->flush();

        return $this->redirectToRoute('app_comments', ['id' => $comment->getPost()->getId()]);
    }

    #[Route('/comment/{id}/delete', name: 'app_comment_delete', methods: ['POST'])]
    public function delete(
        int $id,
        CommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository
    ): RedirectResponse {
        $utilisateur = $this->getForumUser($userRepository);
        $comment = $commentRepository->find($id);

        if (!$comment || !$comment->getPost()) {
            return $this->redirectToRoute('app_forum');
        }

        if (!$utilisateur || !$comment->getAuthor() || $comment->getAuthor()->getId() !== $utilisateur->getId()) {
            return $this->redirectToRoute('app_comments', ['id' => $comment->getPost()->getId()]);
        }

        if ($comment->getPost()->isLocked()) {
            $this->addFlash('error', 'Ce post est verrouillé.');
            return $this->redirectToRoute('app_comments', ['id' => $comment->getPost()->getId()]);
        }

        $comment->setStatus('DELETED');
        $entityManager->flush();

        return $this->redirectToRoute('app_comments', ['id' => $comment->getPost()->getId()]);
    }
}




