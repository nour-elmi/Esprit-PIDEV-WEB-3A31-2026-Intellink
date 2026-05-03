<?php

namespace App\Controller;

use App\Controller\Concern\ResolvesForumUser;
use App\Entity\Image;
use App\Entity\Post;
use App\Repository\UserRepository;
use App\Service\ProfanityService;
use App\Service\SuggestionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PostController extends AbstractController
{
    use ResolvesForumUser;

    #[Route('/post/create', name: 'app_post_create')]
public function create(
    Request $request,
    EntityManagerInterface $entityManager,
    UserRepository $userRepository,
    ProfanityService $profanityService
): Response {
    $utilisateur = $this->getForumUser($userRepository);

    if ($utilisateur === null) {
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => false,
                'message' => 'Vous devez etre connecte.'
            ], 401);
        }

        $this->addFlash('error', 'Vous devez etre connecte.');
        return $this->redirectToRoute('app_login');
    }

    if ($request->isMethod('POST')) {
        $content = trim((string) $request->request->get('content', ''));
        $uploadedFiles = $request->files->all('images');

        if ($content === '' || mb_strlen($content) < 5 || mb_strlen($content) > 500) {
            return $this->json([
                'success' => false,
                'message' => 'Le post doit contenir entre 5 et 500 caractères.'
            ], 400);
        }

        try {
            $cleanedContent = $profanityService->cleanText($content);

            if ($cleanedContent !== '' && $cleanedContent !== $content) {
                $content = $cleanedContent;
            }
        } catch (\Throwable) {}

        $post = new Post();
        $post->setAuthor($utilisateur);
        $post->setContent($content);
        $post->setStatus('ACTIVE');
        $post->setIsEdited(false);
        $post->setIsPinned(false);
        $post->setIsLocked(false);

        $entityManager->persist($post);
        $entityManager->flush();

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/posts';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($uploadedFiles as $file) {
            if (!$file instanceof UploadedFile) continue;

            $filename = uniqid() . '.' . ($file->guessExtension() ?: 'jpg');

            try {
                $file->move($uploadDir, $filename);

                $img = new Image();
                $img->setPost($post);
                $img->setImageUrl($filename);

                $entityManager->persist($img);
            } catch (FileException) {}
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'redirect' => $this->generateUrl('app_forum')
        ]);
    }

    return $this->render('post/create.html.twig');
}

    #[Route('/post/suggestions', name: 'app_post_suggestions', methods: ['GET'])]
    public function suggestions(
        Request $request,
        SuggestionService $suggestionService
    ): JsonResponse {
        $query = trim((string) $request->query->get('q', ''));
        $limit = (int) $request->query->get('limit', 6);

        if (mb_strlen($query) < 3) {
            return $this->json([
                'success' => true,
                'suggestions' => [],
            ]);
        }

        try {
            $suggestions = $suggestionService->getTechSuggestions($limit);

            return $this->json([
                'success' => true,
                'suggestions' => $suggestions,
            ]);
        } catch (\Throwable) {
            return $this->json([
                'success' => false,
                'suggestions' => [],
                'message' => 'Erreur lors du chargement des suggestions.',
            ], 500);
        }
    }
}