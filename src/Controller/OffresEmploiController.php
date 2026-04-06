<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\OffreEmploiRepository;
use App\Entity\OffreEmploi;
use App\Form\OffreFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use App\Repository\ListeParticipationRepository;


final class OffresEmploiController extends AbstractController
{
    #[Route('/showOffre', name:'showOffre')]
    public function listOffresfromDB(Request $request, OffreEmploiRepository $repo, PaginatorInterface $paginator): Response 
    {
        $sortBy = $request->query->get('sortBy');
        $direction = $request->query->get('direction', 'ASC'); // ASC par défaut
        $searchTerm = $request->query->get('query');

        if ($sortBy) {
            $offres = $repo->sortOffres($sortBy, $direction);
        } 
        else {
            $offres = $repo->searchOffres($searchTerm); 
        }

        $offres = $paginator->paginate(
            $offres, 
            $request->query->getInt('page', 1), 
            4 
        );

        return $this->render('offres_emploi/frontend/showOffre.html.twig', [
            "list" => $offres
        ]);
        }

    #[Route('/showOffreUser', name:'showOffreUser')]
    public function listOffresUfromDB(Request $request, OffreEmploiRepository $repo, PaginatorInterface $paginator): Response 
    {
        $searchTerm = $request->query->get('query');

        $offres = $repo->searchOffres($searchTerm);

        $offres = $paginator->paginate(
            $offres, 
            $request->query->getInt('page', 1), 
            4 
        );

        return $this->render('offres_emploi/frontend/showOffreUser.html.twig', [
            "list" => $offres
        ]);
    }

    #[Route('/deleteOffre/{id}', name:'deleteOffre')]
    public function deleteOffre($id, ManagerRegistry $Manager, OffreEmploiRepository $repo)
    {
        $em= $Manager->getManager();
        $newOffre= $repo->find($id);
        $em->remove($newOffre);
        $em->flush();
        return $this->redirectToRoute('showOffre');

    }

    #[Route('/updateOffre/{id}', name:'updateOffre')]
    public function updateOffre($id, ManagerRegistry $Manager, OffreEmploiRepository $repo, Request $request)
    {
        $em = $Manager->getManager();
        $offre = $repo->find($id); 
        
        $form = $this->createForm(OffreFormType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush(); 
            return $this->redirectToRoute('showOffre'); 
        }

        return $this->render('offres_emploi/frontend/updateOffre.html.twig', [
            'formOffre' => $form->createView(),
            'offre' => $offre
        ]);
    }

    #[Route('/addOffre', name:'addOffre')]
public function addOffre(ManagerRegistry $Manager, Request $request): Response
{
    $em = $Manager->getManager();
    $newOffre = new OffreEmploi();
    $form = $this->createForm(OffreFormType::class, $newOffre);
    $form->handleRequest($request);

    if ($form->isSubmitted()) {
        // --- DEBUT DES CONTROLES DE SAISIE ---
        $errors = [];

        // 1. Contrôle du nom de l'entreprise (Min 5 caractères)
        if (strlen($newOffre->getNomEntreprise()) < 5) {
            $errors[] = "Le nom de l'entreprise est trop court (minimum 5 caractères).";
        }

        // 2. Contrôle de cohérence des dates
        $dateDebut = $newOffre->getDateDebut();
        $dateFin = $newOffre->getDateExpiration();
        if ($dateDebut && $dateFin && $dateDebut > $dateFin) {
            $errors[] = "La date de début ne peut pas être postérieure à la date d'expiration.";
        }

        // 3. Contrôle du salaire (Doit être positif)
        if ($newOffre->getSalaire() <= 0) {
            $errors[] = "Le salaire doit être un montant positif.";
        }

        // 4. Contrôle de la description (Qualité du contenu)
        if (strlen($newOffre->getDescription()) < 20) {
            $errors[] = "La description doit contenir au moins 20 caractères pour être valide.";
        }

        // --- TRAITEMENT SI PAS D'ERREURS ---
        if (empty($errors) && $form->isValid()) {
            $newOffre->setIdUser(1); // Ton ID statique
            $em->persist($newOffre);
            $em->flush();

            $this->addFlash('success', 'Félicitations ! L\'offre pour ' . $newOffre->getNomEntreprise() . ' est en ligne.');
            return $this->redirectToRoute('showOffre');
        } else {
            // Sinon, on envoie toutes les erreurs dans les flash messages
            foreach ($errors as $error) {
                $this->addFlash('danger', $error);
            }
        }
    }

    return $this->render('offres_emploi/frontend/addOffre.html.twig', [
        'formOffre' => $form->createView(),
    ]);
}

    public function index(Request $request, OffreEmploiRepository $repository): Response
{
    $searchTerm = $request->query->get('query'); 

    $offres = $repository->searchOffres($searchTerm);

    return $this->render('offre_emploi/index.html.twig', [
        'offres' => $offres,
    ]);
}

#[Route('/back/offres', name: 'app_back_offres')]
public function listOffresBack(
    Request $request, 
    OffreEmploiRepository $repo, 
    ListeParticipationRepository $partRepo, 
    PaginatorInterface $paginator
): Response 
{
    $searchTerm = $request->query->get('query');

    // 1. Calcul des statistiques complexes
    $allOffres = $repo->findAll();
    $totalOffres = count($allOffres);
    
    // Taux d'attractivité
    $totalParticipations = count($partRepo->findAll());
    $attractivite = $totalOffres > 0 ? round($totalParticipations / $totalOffres, 1) : 0;

    // Taux d'urgence
    $now = new \DateTime();
    $soon = (new \DateTime())->modify('+2 days');
    // On compte manuellement si la méthode n'existe pas encore dans le repo
    $offresUrgentes = 0;
    foreach ($allOffres as $o) {
        if ($o->getDateExpiration() && $o->getDateExpiration() >= $now && $o->getDateExpiration() <= $soon) {
            $offresUrgentes++;
        }
    }
    $tauxUrgence = $totalOffres > 0 ? round(($offresUrgentes / $totalOffres) * 100) : 0;

    // 2. Gestion de la pagination pour la table
    $offresQuery = $repo->searchOffres($searchTerm); 
    $pagination = $paginator->paginate(
        $offresQuery, 
        $request->query->getInt('page', 1), 
        5 
    );

    // 3. Envoi de TOUTES les variables au template
    return $this->render('offres_emploi/backend/BackOffre.html.twig', [
        "offres" => $pagination,
        'statAttractivite' => $attractivite,
        'statUrgence' => $tauxUrgence,
        'totalCandidats' => $totalParticipations
    ]);
}

#[Route('/back/offres/delete/{id}', name: 'deleteBackOffre')]
public function deleteBackOffre($id, ManagerRegistry $Manager, OffreEmploiRepository $repo): Response
{
    $em = $Manager->getManager();
    $offre = $repo->find($id);
    
    if ($offre) {
        $em->remove($offre);
        $em->flush();
    }

    return $this->redirectToRoute('app_back_offres');
}

public function listBackOffres(OffreEmploiRepository $repo, ListeParticipationRepository $partRepo) {
    $offres = $repo->findAll();
    $totalOffres = count($offres);
    
    // 1. Calcul du taux d'attractivité moyen
    $totalParticipations = count($partRepo->findAll());
    $attractivite = $totalOffres > 0 ? round($totalParticipations / $totalOffres, 1) : 0;

    // 2. Calcul du taux d'urgence (expirent bientôt)
    $now = new \DateTime();
    $soon = (new \DateTime())->modify('+2 days');
    $offresUrgentes = $repo->countByExpirationDate($now, $soon); // Nécessite une méthode dans le Repository
    $tauxUrgence = $totalOffres > 0 ? round(($offresUrgentes / $totalOffres) * 100) : 0;

    return $this->render('offres_emploi/backend/index.html.twig', [
        'offres' => $offres,
        'statAttractivite' => $attractivite,
        'statUrgence' => $tauxUrgence,
        'totalCandidats' => $totalParticipations
    ]);
}

}
