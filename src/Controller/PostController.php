<?php

namespace App\Controller;

use App\Entity\Image;
use App\Entity\Post;
use App\Repository\UserRepository;
use App\Service\ProfanityService;
use App\Service\SuggestionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PostController extends AbstractController
{
    #[Route('/post/create', name: 'app_post_create')]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        ProfanityService $profanityService
    ): Response {
        $currentUserId = 3; // static test user

        $error = null;
        $warning = null;

        if ($request->isMethod('POST')) {
            $content = trim((string) $request->request->get('content', ''));
            $uploadedFiles = $request->files->all('images');

            if ($content === '') {
                $error = 'Le contenu du post est obligatoire.';
            } elseif (mb_strlen($content) < 5 || mb_strlen($content) > 500) {
                $error = 'Le post doit contenir entre 5 et 500 caractères.';
            } else {
                try {
                    $cleanedContent = $profanityService->cleanText($content);

                    if ($cleanedContent !== '' && $cleanedContent !== $content) {
                        $warning = 'Des mots inappropriés ont été détectés. Le contenu a été nettoyé automatiquement.';
                        $content = $cleanedContent;
                    }
                } catch (\Throwable $e) {
                    // API failed → keep original text
                }

                $author = $userRepository->find($currentUserId);

                if (!$author) {
                    $error = 'Utilisateur de test introuvable.';
                } else {
                    $post = new Post();
                    $post->setAuthor($author);
                    $post->setContent($content);
                    $post->setStatus('ACTIVE');
                    $post->setIsEdited(false);
                    $post->setIsPinned(false);
                    $post->setIsLocked(false);

                    $entityManager->persist($post);
                    $entityManager->flush();

                    if (is_array($uploadedFiles)) {
                        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/posts';

                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }

                        foreach ($uploadedFiles as $uploadedFile) {
                            if ($uploadedFile) {
                                $newFilename = uniqid('', true) . '.' . $uploadedFile->guessExtension();

                                try {
                                    $uploadedFile->move($uploadDir, $newFilename);

                                    $image = new Image();
                                    $image->setPost($post);
                                    $image->setImageUrl($newFilename);

                                    $entityManager->persist($image);
                                } catch (FileException $e) {
                                    $error = 'Erreur lors de l’upload d’image.';
                                    break;
                                }
                            }
                        }
                    }

                    if ($error === null) {
                        $entityManager->flush();

                        if ($warning !== null) {
                            $this->addFlash('warning', $warning);
                        }

                        $this->addFlash('success', 'Post publié avec succès.');
                        return $this->redirectToRoute('app_forum');
                    }
                }
            }
        }

        return $this->render('post/create.html.twig', [
            'error' => $error,
            'warning' => $warning,
        ]);
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
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'suggestions' => [],
                'message' => 'Erreur lors du chargement des suggestions.',
            ], 500);
        }
    }
}