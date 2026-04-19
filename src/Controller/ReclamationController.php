<?php

namespace App\Controller;

use App\Entity\Reclamation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/reclamation')]
class ReclamationController extends AbstractController
{
    // ==========================================
    // 1. LISTE DES RÉCLAMATIONS
    // ==========================================
    #[Route('/', name: 'app_reclamation_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }

        // CORRECTION ICI : On utilise 'utilisateur' et 'date_creation' pour correspondre à votre entité
        $reclamations = $entityManager->getRepository(Reclamation::class)->findBy(
            ['utilisateur' => $user], 
            ['date_creation' => 'DESC']
        );

        return $this->render('frontUser/reclamation/index.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    // ==========================================
    // 2. CRÉATION
    // ==========================================
    #[Route('/new', name: 'app_reclamation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }

        if ($request->isMethod('POST')) {
            $reclamation = new Reclamation();
            
            // CORRECTION ICI : setUtilisateur au lieu de setUser
            $reclamation->setUtilisateur($user);
            
            $reclamation->setObjet($request->request->get('objet'));
            $reclamation->setType($request->request->get('type'));
            $reclamation->setPriorite($request->request->get('priorite'));
            $reclamation->setDescription($request->request->get('description'));
            $reclamation->setStatut('OUVERT');
            $reclamation->setDateCreation(new \DateTime());

            $file = $request->files->get('pieceJointe');
            if ($file) {
                $newFilename = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid().'.'.$file->guessExtension();
                $file->move($this->getParameter('reclamations_directory'), $newFilename);
                $reclamation->setPieceJointe($newFilename);
            }

            $entityManager->persist($reclamation);
            $entityManager->flush();

            $this->addFlash('success', 'Votre réclamation a été envoyée avec succès.');
            return $this->redirectToRoute('app_reclamation_index');
        }

        return $this->render('frontUser/reclamation/new.html.twig');
    }

    // ==========================================
    // 3. CONSULTATION & MODIFICATION
    // ==========================================
    #[Route('/{id}', name: 'app_reclamation_show', methods: ['GET', 'POST'])]
    public function show(Reclamation $reclamation, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // CORRECTION ICI : getUtilisateur() au lieu de getUser()
        if ($reclamation->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if ($request->isMethod('POST') && $reclamation->getStatut() === 'OUVERT') {
            $reclamation->setObjet($request->request->get('objet'));
            $reclamation->setType($request->request->get('type'));
            $reclamation->setPriorite($request->request->get('priorite'));
            $reclamation->setDescription($request->request->get('description'));

            $file = $request->files->get('pieceJointe');
            if ($file) {
                $newFilename = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid().'.'.$file->guessExtension();
                $file->move($this->getParameter('reclamations_directory'), $newFilename);
                $reclamation->setPieceJointe($newFilename);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Votre réclamation a été modifiée avec succès.');
            return $this->redirectToRoute('app_reclamation_show', ['id' => $reclamation->getId()]);
        }

        return $this->render('frontUser/reclamation/show.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    // ==========================================
    // 4. SUPPRESSION
    // ==========================================
    #[Route('/{id}/delete', name: 'app_reclamation_delete', methods: ['POST'])]
    public function delete(Reclamation $reclamation, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($reclamation->getStatut() === 'OUVERT' && $this->isCsrfTokenValid('delete'.$reclamation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($reclamation);
            $entityManager->flush();
            $this->addFlash('success', 'Réclamation supprimée définitivement.');
        }
        return $this->redirectToRoute('app_reclamation_index');
    }

    // ==========================================
    // 5. IA: ANALYSE DE LA DESCRIPTION (GEMINI)
    // ==========================================
    #[Route('/ai/analyze', name: 'app_reclamation_ai_analyze', methods: ['POST'])]
    public function analyze(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $description = $data['description'] ?? '';

        if (empty($description)) {
            return new JsonResponse(['error' => 'Description vide'], 400);
        }

        // Remplacez cette clé par votre clé API Google Gemini réelle
        // L'idéal est de la mettre dans le .env: GEMINI_API_KEY et d'utiliser $this->getParameter('gemini_api_key')
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? 'METTEZ_VOTRE_CLE_API_ICI';

        if ($apiKey === 'METTEZ_VOTRE_CLE_API_ICI') {
            return new JsonResponse([
                'suggestion' => "<strong>Note:</strong> L'API Gemini n'est pas encore configurée.<br><br><strong>Analyse simulée:</strong> D'après les mots clés détectés, nous vous suggérons de redémarrer le système, de vérifier vos câbles ou de consulter notre FAQ sur le problème que vous rencontrez."
            ]);
        }

        try {
            $prompt = "Tu es un assistant support technique expert. L'utilisateur rencontre le problème suivant : '$description'. 
            Anticipe le problème, explique brièvement pourquoi cela arrive et propose une solution en 3 ou 4 phrases maximum.";

            $response = $httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey, [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ]
                ]
            ]);

            // Gestion des erreurs Google API (400, 403, 404, etc)
            if ($response->getStatusCode() !== 200) {
                 return new JsonResponse(['suggestion' => 'Erreur API: Code ' . $response->getStatusCode() . ' - Veuillez vérifier votre clé API ou votre URL Google Gemini.']);
            }

            $result = $response->toArray();
            
            // Extraction de la réponse de Gemini
            $suggestion = $result['candidates'][0]['content']['parts'][0]['text'] ?? "Désolé, l'IA n'a pas pu formuler de réponse.";
            
            // Convertir les retours à la ligne en balises br pour le HTML
            $suggestionHTML = nl2br(htmlspecialchars($suggestion));

            return new JsonResponse(['suggestion' => $suggestionHTML]);
            
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/read', name: 'app_reclamation_read', methods: ['POST'])]
    public function markAsRead(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): JsonResponse
    {
        $reclamation->setIsRead(true);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}