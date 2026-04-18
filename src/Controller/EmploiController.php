<?php

namespace App\Controller;

use App\Entity\Emploi;
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
use Gemini\Client;
use App\Service\AdzunaService;


final class EmploiController extends AbstractController
{
    #[Route('/showoffre', name: 'showoffre')]
    public function listOffresfromDB(EmploiRepository $repo, Request $request, PaginatorInterface $paginator)
    {
        $searchTerm = $request->query->get('search');
        $data = $repo->searchByTerm($searchTerm);

        $offres = $paginator->paginate(
            $data,
            $request->query->getInt('page', 1),
            6
        );

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
    
        $queryBuilder = $repo->createQueryBuilder('e');

        if ($searchTerm) {
            $queryBuilder->where('e.titre LIKE :term OR e.nom_entreprise LIKE :term') // nom_entreprise avec underscore comme dans l'entité
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

        // --- Garde tes statistiques inchangées ---
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
    public function listOffresRfromDB(EmploiRepository $repo, Request $request, PaginatorInterface $paginator)
    {
        $searchTerm = $request->query->get('search');
        $sortBy = $request->query->get('sortBy');
        
        // Logique de tri existante
        if ($sortBy) {
            if ($sortBy == 'salaire_desc') { $data = $repo->sortByField('salaire', 'DESC'); }
            elseif ($sortBy == 'salaire_asc') { $data = $repo->sortByField('salaire', 'ASC'); }
            elseif ($sortBy == 'expiration_asc') { $data = $repo->sortByField('date_expiration', 'ASC'); }
            else { $data = $repo->findAll(); }
        } elseif ($searchTerm) {
            $data = $repo->searchByTerm($searchTerm);
        } else {
            $data = $repo->findAll();
        }

        // On pagine le résultat final
        $offres = $paginator->paginate(
            $data,
            $request->query->getInt('page', 1),
            4 // Petit nombre pour tester la pagination facilement
        );

        return $this->render('emploi/front/showOffreR.html.twig', [
            'offre' => $offres,
            'searchTerm' => $searchTerm,
            'currentSort' => $sortBy
        ]);
    }

    #[Route('/addOffre', name:'addOffre')]
    public function addOffre(ManagerRegistry $Manager, Request $request)
    {
        $em = $Manager->getManager();
        $newOffre= new Emploi();
        $form= $this->createForm(EmploiType::class, $newOffre);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $newOffre->setIdUser(1); 
            $em->persist($newOffre);
            $em->flush();
            return $this->redirectToRoute('showoffreRecruteur');
        }
        $em->flush();
        return $this->render('emploi/front/addOffre.html.twig', ['formOffre' => $form]);
    }

    #[Route('/deleteOffre/{id}', name:'deleteOffre')]
    public function deleteOffre($id, ManagerRegistry $Manager, EmploiRepository $repo)
    {
        $em= $Manager->getManager();
        $newOffre= $repo->find($id);
        $em->remove($newOffre);
        $em->flush();
        return $this->redirectToRoute('showoffreRecruteur');
    }

    #[Route('/updateOffre/{id}', name:'updateOffre')]
    public function updateOffre($id, ManagerRegistry $Manager, EmploiRepository $repo, Request $request)
    {
        $em = $Manager->getManager();
        $offre = $repo->find($id);
        if (!$offre) {
            throw $this->createNotFoundException("L'offre avec l'ID $id n'existe pas.");
        }
        $form = $this->createForm(EmploiType::class, $offre);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush(); 
            return $this->redirectToRoute('showoffreRecruteur');
        }
        return $this->render('emploi/front/addOffre.html.twig', [
            'formOffre' => $form->createView()
        ]);
    }

    private Client $geminiClient;

    public function __construct(Client $geminiClient)
    {
        $this->geminiClient = $geminiClient;
    }

    #[Route('/autocomplete-desc', name: 'api_description_autocomplete', methods: ['POST'])]
    public function autocomplete(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $currentText = $data['text'] ?? '';
        $jobTitle = $data['titre'] ?? '';
        $companyName = $data['nom_entreprise'] ?? '';

        if (strlen($currentText) < 10) {
            return new JsonResponse(['suggestion' => '']);
        }

        $prompt = "Tu es un expert RH. Complète cette description de poste : \"$currentText\"";
        if ($jobTitle) $prompt .= " pour le poste de $jobTitle";
        if ($companyName) $prompt .= " chez $companyName";
        $prompt .= ". Réponds uniquement par la suite du texte de façon concise. Commence directement par la continuation, sans aucun mot d'introduction et essaie de ne pas generer une longue paragraphe mais aussi pas moins de 4 lignes.";
        try {
            $result = $this->geminiClient->generativeModel(model: 'gemini-2.5-flash-lite')->generateContent($prompt);
            $suggestion = $result->text();
            $suggestion = preg_replace('/^(Absolument|Oui|Bien sûr|Voici|D\'accord|Désolé|Bonjour).*?\.\s*/i', '', $suggestion);
            return new JsonResponse(['suggestion' => trim($suggestion)]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/market-salary', name: 'api_market_salary', methods: ['GET'])]
    public function getMarketSalary(Request $request, AdzunaService $adzunaService): JsonResponse
    {
        $jobTitle = $request->query->get('titre');
        
        if (!$jobTitle) {
            return new JsonResponse(['error' => 'Titre manquant'], 400);
        }

        $data = $adzunaService->getSalaryStats($jobTitle);
        
        // On extrait la moyenne (Adzuna renvoie souvent un tableau de dates/valeurs)
        // On simplifie pour renvoyer la dernière valeur connue
        $average = !empty($data['month']) ? end($data['month']) : null;

        return new JsonResponse([
            'average' => $average,
            'currency' => 'EUR' // Adzuna FR est souvent en EUR, tu pourras convertir en DT si besoin
        ]);
    }
}
