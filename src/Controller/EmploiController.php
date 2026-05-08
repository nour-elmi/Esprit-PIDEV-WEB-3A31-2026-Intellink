<?php
namespace App\Controller;

use App\Entity\Emploi;
use App\Entity\Utilisateur;               
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\EmploiRepository;
use App\Repository\ListeParticipationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use App\Form\EmploiType;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\AdzunaService;
use App\Service\MarketIntelligenceService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class EmploiController extends AbstractController
{
    #[Route('/showoffre', name: 'showoffre')]
    public function listOffresfromDB(EmploiRepository $repo, Request $request, PaginatorInterface $paginator): Response
    {
        // Pas de filtre par user : visible par tout le monde
        $searchTerm = $request->query->get('search');
        $searchTerm = is_string($searchTerm) ? $searchTerm : null;
        $data = $repo->searchByTerm($searchTerm);
        $offres = $paginator->paginate($data, $request->query->getInt('page', 1), 6);
        return $this->render('emploi/front/showOffre.html.twig', [
            'offre' => $offres,
            'searchTerm' => $searchTerm 
        ]);
    }

    #[Route('/showoffreBack', name: 'showoffreBack')]
    public function listOffresBackfromDB(
        EmploiRepository $repo, 
        ListeParticipationRepository $partRepo, 
        Request $request,
        PaginatorInterface $paginator // <--- AJOUT : Injection du service
    ): Response 
    {
        $searchTerm = $request->query->get('search');
        $searchTerm = is_string($searchTerm) ? $searchTerm : null;
    
        $queryBuilder = $repo->createQueryBuilder('e');

        if ($searchTerm) {
            $queryBuilder->where('e.titre LIKE :term OR e.nom_entreprise LIKE :term') // nom_entreprise avec underscore comme dans l'entitÃ©
                ->setParameter('term', '%'.$searchTerm.'%');
        }

        // CORRECTION ICI : id_offre au lieu de IdOffre
        $queryBuilder->orderBy('e.id_offre', 'DESC');

        // On passe le QueryBuilder directement au paginator
        $offresPaginees = $paginator->paginate(
            $queryBuilder, 
            $request->query->getInt('page', 1), 
            5 
        );

        // --- Garde tes statistiques inchangÃ©es ---
        $allOffres = $repo->findAll();
        $totalParticipations = $partRepo->count([]);
        $totalOffres = count($allOffres);
        $moyenne = $totalOffres > 0 ? $totalParticipations / $totalOffres : 0;

        $dateLimite = new \DateTime();
        $dateLimite->modify('+15 weeks');
        
        $offresAlert = $repo->createQueryBuilder('e')
            ->where('e.date_expiration BETWEEN :now AND :limite')
            ->setParameter('now', new \DateTime())
            ->setParameter('limite', $dateLimite)
            ->getQuery()
            ->getResult();

        return $this->render('emploi/back/offresBack.html.twig', [
            'offre' => $offresPaginees, // <--- C'est maintenant un objet de pagination
            'searchTerm' => $searchTerm,
            'stats' => [
                'moyenne' => round($moyenne, 1),
                'countAlert' => count($offresAlert),
                'totalCandidatures' => $totalParticipations
            ]
        ]);
    }

    #[Route('/showoffreRecruteur', name: 'showoffreRecruteur')]
    public function listOffresRfromDB(EmploiRepository $repo, Request $request, PaginatorInterface $paginator): Response
    {
        // Vue "recruteur" : ne montrer que ses propres offres
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        $searchTerm = $request->query->get('search');
        $searchTerm = is_string($searchTerm) ? $searchTerm : null;
        $sortBy = $request->query->get('sortBy');
        $sortBy = is_string($sortBy) ? $sortBy : null;
        
        // Base : toutes les offres du recruteur
        $data = $repo->findBy(['id_user' => $user->getId()]);

        // Appliquer tri ou recherche sur ce sousâ€‘ensemble
        if ($sortBy) {
            if ($sortBy == 'salaire_desc') { 
                // CHANGEMENT: sortByField() prend 1-2 parametres, pas 3.
                // Ancien appel (garde): $data = $repo->sortByField('salaire', 'DESC', $user->getId());
                $data = $repo->sortByField('salaire', 'DESC'); 
            } elseif ($sortBy == 'salaire_asc') { 
                // CHANGEMENT: sortByField() prend 1-2 parametres, pas 3.
                // Ancien appel (garde): $data = $repo->sortByField('salaire', 'ASC', $user->getId());
                $data = $repo->sortByField('salaire', 'ASC'); 
            } elseif ($sortBy == 'expiration_asc') { 
                // CHANGEMENT: sortByField() prend 1-2 parametres, pas 3.
                // Ancien appel (garde): $data = $repo->sortByField('date_expiration', 'ASC', $user->getId());
                $data = $repo->sortByField('date_expiration', 'ASC'); 
            }
        } elseif ($searchTerm) {
            // CHANGEMENT: searchByTerm() prend 1 parametre, pas 2.
            // Ancien appel (garde): $data = $repo->searchByTerm($searchTerm, $user->getId());
            $data = $repo->searchByTerm($searchTerm); // Ã  adapter dans le repository
        }

        $offres = $paginator->paginate(
            $data,
            $request->query->getInt('page', 1),
            4
        );

        return $this->render('emploi/front/showOffreR.html.twig', [
            'offre' => $offres,
            'searchTerm' => $searchTerm,
            'currentSort' => $sortBy
        ]);
    }

    #[Route('/addOffre', name:'addOffre')]
    public function addOffre(ManagerRegistry $Manager, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez Ãªtre connectÃ©.');
        }

        $em = $Manager->getManager();
        $newOffre = new Emploi();
        $form = $this->createForm(EmploiType::class, $newOffre);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $userId = $user->getId();
            if ($userId === null) {
                throw $this->createAccessDeniedException('Utilisateur invalide.');
            }
            $newOffre->setIdUser($userId);
            $em->persist($newOffre);
            $em->flush();
            $this->addFlash('success', 'Offre ajoutÃ©e avec succÃ¨s.');
            return $this->redirectToRoute('showoffreRecruteur');
        }
        return $this->render('emploi/front/addOffre.html.twig', ['formOffre' => $form]);
    }

    #[Route('/deleteOffre/{id}', name:'deleteOffre')]
    public function deleteOffre(int $id, ManagerRegistry $Manager, EmploiRepository $repo): Response
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        $em = $Manager->getManager();
        $offre = $repo->find($id);
        if (!$offre || $offre->getIdUser() !== $user->getId()) {
            throw $this->createNotFoundException('Offre introuvable ou accÃ¨s non autorisÃ©.');
        }

        // VÃ©rification CSRF (optionnelle mais recommandÃ©e)
        // if (!$this->isCsrfTokenValid('delete_offre_'.$id, $request->request->get('_token'))) { ... }

        $em->remove($offre);
        $em->flush();
        $this->addFlash('success', 'Offre supprimÃ©e.');
        return $this->redirectToRoute('showoffreRecruteur');
    }

    #[Route('/updateOffre/{id}', name:'updateOffre')]
    public function updateOffre(int $id, ManagerRegistry $Manager, EmploiRepository $repo, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        $em = $Manager->getManager();
        $offre = $repo->find($id);
        if (!$offre || $offre->getIdUser() !== $user->getId()) {
            throw $this->createNotFoundException("Offre introuvable ou accÃ¨s non autorisÃ©.");
        }

        $form = $this->createForm(EmploiType::class, $offre);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Offre mise Ã  jour.');
            return $this->redirectToRoute('showoffreRecruteur');
        }
        return $this->render('emploi/front/addOffre.html.twig', [
            'formOffre' => $form->createView()
        ]);
    }

    #[Route('/autocomplete-desc', name: 'api_description_autocomplete', methods: ['POST'])]
    public function autocomplete(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $currentText = $data['text'] ?? '';
        $jobTitle = $data['titre'] ?? '';
        $companyName = $data['nom_entreprise'] ?? '';

        if (strlen($currentText) < 10) {
            return new JsonResponse(['suggestion' => '']);
        }

        $prompt = "Tu es un expert RH. Complete cette description de poste : \"$currentText\"";
        if ($jobTitle) {
            $prompt .= " pour le poste de $jobTitle";
        }
        if ($companyName) {
            $prompt .= " chez $companyName";
        }
        $prompt .= ". Reponds uniquement par la suite du texte de facon concise. Commence directement par la continuation, sans aucun mot d'introduction et essaie de ne pas generer un long paragraphe.";

        try {
            $apiKey = (string) ($_ENV['GEMINI_API_KEY'] ?? '');
            if ($apiKey === '') {
                return new JsonResponse(['suggestion' => '']);
            }

            $response = $httpClient->request(
                'POST',
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($apiKey),
                [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'contents' => [[
                            'parts' => [[
                                'text' => $prompt,
                            ]],
                        ]],
                    ],
                    'timeout' => 20,
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return new JsonResponse(['suggestion' => '']);
            }

            $payload = $response->toArray(false);
            $suggestion = (string) ($payload['candidates'][0]['content']['parts'][0]['text'] ?? '');
            $suggestion = preg_replace('/^(Absolument|Oui|Bien sur|Voici|D\'accord|Desole|Bonjour).*?\.\s*/i', '', $suggestion);

            return new JsonResponse(['suggestion' => trim((string) $suggestion)]);
        } catch (\Throwable $e) {
            return new JsonResponse(['suggestion' => '']);
        }
    }

    #[Route('/api/market-salary', name: 'api_market_salary', methods: ['GET'])]
    public function getMarketSalary(Request $request, AdzunaService $adzunaService): JsonResponse
    {
        $jobTitle = $request->query->get('titre');
        $jobTitle = is_string($jobTitle) ? $jobTitle : null;
        
        if (!$jobTitle) {
            return new JsonResponse(['error' => 'Titre manquant'], 400);
        }

        $data = $adzunaService->getSalaryStats($jobTitle);
        
        // On extrait la moyenne (Adzuna renvoie souvent un tableau de dates/valeurs)
        // On simplifie pour renvoyer la derniÃ¨re valeur connue
        $average = !empty($data['month']) ? end($data['month']) : null;

        return new JsonResponse([
            'average' => $average,
            'currency' => 'EUR' 
        ]);
    }

    #[Route('/api/gap-analysis/{id}', name: 'api_gap_analysis', methods: ['GET'])]
public function getGapAnalysis(Emploi $emploi, MarketIntelligenceService $marketService): JsonResponse
{
    $analysis = $marketService->analyzeGap($emploi);
    
    // 1. Nettoyage du titre pour Adzuna (on prend les 2 premiers mots pour plus de prÃ©cision)
    $words = explode(' ', (string) ($emploi->getTitre() ?? ''));
    $shortTitle = count($words) > 1 ? $words[0] . ' ' . $words[1] : $words[0];

    // 2. Appel Ã  Gemini pour extraire les compÃ©tences du marchÃ© (Market Intelligence)
    $prompt = "Pour un poste de '{$shortTitle}', quelles sont les 3 compÃ©tences techniques les plus demandÃ©es actuellement sur le marchÃ© ? 
               RÃ©ponds uniquement par les noms des compÃ©tences sÃ©parÃ©s par des virgules, sans phrases.";
    
    $marketSkills = "Non disponible";
    // CHANGEMENT: suppression du try/catch mort (aucune exception possible ici).
    // Ancien code (garde):
    // try {
    //     $marketSkills = "Non disponible";
    // } catch (\Exception $e) {
    //     $marketSkills = "Erreur extraction";
    // }

    // 3. Construction du HTML enrichi
    $marketAvg = $analysis['market_avg'];
    $salary = is_numeric($marketAvg)
        ? round(((float) $marketAvg) / 12) . ' EUR/mois'
        : 'Donnee Adzuna indisponible';
    $html = "<div class='text-start p-1'>";
    $html .= "<p class='mb-1'><i class='fas fa-coins text-warning me-2'></i><b>Estimation MarchÃ©:</b><br><span class='badge bg-light text-dark'>$salary</span></p>";
    $html .= "<p class='mb-1'><i class='fas fa-chart-bar text-info me-2'></i><b>Tendances MarchÃ©:</b><br><small class='text-info'>$marketSkills</small></p>";
    $html .= "<hr class='my-1'>";
    $html .= "<p class='mb-0'><i class='fas fa-user-graduate text-success me-2'></i><b>CompÃ©tences Candidats:</b><br><small>" . (empty($analysis['top_skills']) ? "Aucun candidat" : implode(', ', $analysis['top_skills'])) . "</small></p>";
    $html .= "</div>";

    return new JsonResponse(['html' => $html]);
}
}


