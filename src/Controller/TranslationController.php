<?php

namespace App\Controller;

use App\Service\TranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class TranslationController extends AbstractController
{
    #[Route('/translate', name: 'app_translate', methods: ['POST'])]
    public function translate(
        Request $request,
        TranslationService $translationService
    ): JsonResponse {
        $text = trim((string) $request->request->get('text', ''));
        $targetLang = trim((string) $request->request->get('targetLang', 'en'));

        if ($text === '') {
            return $this->json([
                'success' => false,
                'message' => 'Texte vide.'
            ], 400);
        }

        try {
            $translated = $translationService->translate($text, 'auto', $targetLang);

            return $this->json([
                'success' => true,
                'translatedText' => $translated,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur de traduction.'
            ], 500);
        }
    }
}