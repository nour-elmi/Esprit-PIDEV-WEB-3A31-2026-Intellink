<?php

namespace App\Controller;

use App\Entity\ListeParticipation;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\OffreEmploi;
use App\Entity\Utilisateur;
use App\Form\ListeFormType; 
use App\Repository\ListeParticipationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\OffreEmploiRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;


final class ListeParticipationController extends AbstractController
{
    #[Route('/showParticipation/{id}', name: 'showParticipation')]
    public function showParticipations(OffreEmploi $offre, Request $request, ListeParticipationRepository $participationRepo, PaginatorInterface $paginator): Response
    {
        $searchTerm = $request->query->get('query');
        
        if ($searchTerm) {
            $participations = $participationRepo->searchParticipations($offre->getIdOffre(), $searchTerm);
        } else {
            $participations = $offre->getParticipations();
        }

        $pagination = $paginator->paginate(
            $participations, 
            $request->query->getInt('page', 1), 
            5 
        );

        return $this->render('liste_participation/frontend/showParticipation.html.twig', [
            'offre' => $offre,
            'participations' => $pagination,
        ]);
    }

    #[Route('/showParticipationUser/{id}', name: 'showParticipationUser')]
public function listParticipationsUfromDB(OffreEmploi $offre, Request $request, ListeParticipationRepository $repo): Response {
    $searchTerm = $request->query->get('query');

    if ($searchTerm) {
        $participations = $repo->searchParticipations($offre, $searchTerm);
    } else {
        $participations = $offre->getParticipations();
    }

    return $this->render('liste_participation/frontend/showParticipationUser.html.twig', [
        "offre" => $offre,   
        "list" => $participations    
    ]);
}

#[Route('/showParticipationBack/{id}', name: 'showParticipationBack')]
public function listParticipationsBfromDB(OffreEmploi $offre, Request $request, ListeParticipationRepository $repo): Response {
    $searchTerm = $request->query->get('query');

    if ($searchTerm) {
        $participations = $repo->searchParticipations($offre, $searchTerm);
    } else {
        $participations = $offre->getParticipations();
    }

    return $this->render('liste_participation/backend/BackListeParticipation.html.twig', [
        "offre" => $offre,   
        "participations" => $participations // CHANGE "list" PAR "participations" ICI
    ]);
}

    #[Route('/deleteParticipation/{id}', name: 'deleteParticipation')]
    //#[IsGranted('ROLE_USER')]
    public function deleteParticipation($id, ManagerRegistry $Manager, ListeParticipationRepository $repo): Response
    {
        $em = $Manager->getManager();
        $participation = $repo->find($id);
        
        if ($participation) {
            $em->remove($participation);
            $em->flush();
        }

        return $this->redirectToRoute('showParticipation');
    }

    #[Route('/updateParticipation/{id}', name: 'updateParticipation')]
    //#[IsGranted('ROLE_USER')]
    public function updateParticipation($id, ManagerRegistry $Manager, ListeParticipationRepository $repo, Request $request): Response
    {
        $em = $Manager->getManager();
        $participation = $repo->find($id);

        $form = $this->createForm(ListeFormType::class, $participation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('showParticipation');
        }

        return $this->render('liste_participation/frontend/updateParticipation.html.twig', [
            'formParticipation' => $form->createView(),
            'participation' => $participation
        ]);
    }

    
    #[Route('/addParticipation/{id_offre}', name: 'addParticipation')]
    public function addParticipation($id_offre, ManagerRegistry $Manager, Request $request, OffreEmploiRepository $offreRepo): Response
    {
        $em = $Manager->getManager();
        $offre = $offreRepo->find($id_offre);
    
        if (!$offre) {
            $this->addFlash('danger', "L'offre demandée n'existe pas.");
            return $this->redirectToRoute('showOffre');
        }
    
        $newParticipation = new ListeParticipation();
        $newParticipation->setDateParticipation(new \DateTime());
        $newParticipation->setIdOffre($offre); 
    
        $form = $this->createForm(ListeFormType::class, $newParticipation);
        $form->handleRequest($request);
    
        if ($form->isSubmitted()) {
            $errors = [];
    
            // 1. Contrôle Nom et Prénom (Min 2 caractères)
            if (strlen($newParticipation->getNomP()) < 2 || strlen($newParticipation->getPrenomP()) < 2) {
                $errors[] = "Le nom et le prénom doivent contenir au moins 2 caractères.";
            }
    
            // 2. Contrôle des compétences (Skills)
            if (strlen($newParticipation->getSkills()) < 10) {
                $errors[] = "Veuillez détailler un peu plus vos compétences (min 10 caractères).";
            }
    
            // 3. Gestion du fichier CV
            $cvFile = $form->get('cv')->getData();
            if (!$cvFile) {
                $errors[] = "L'envoi du CV est obligatoire pour postuler.";
            }
    
            // --- TRAITEMENT SI PAS D'ERREURS ---
            if (empty($errors) && $form->isValid()) {
                if ($cvFile) {
                    $newFilename = uniqid().'.'.$cvFile->guessExtension();
                    try {
                        $cvFile->move(
                            $this->getParameter('cv_directory'),
                            $newFilename
                        );
                        $newParticipation->setCv($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('danger', "Erreur lors de l'upload du fichier.");
                    }
                }
    
                $em->persist($newParticipation);
                $em->flush();
    
                $this->addFlash('success', 'Votre candidature a été envoyée avec succès !');
                return $this->redirectToRoute('showParticipation', ['id' => $id_offre]);
            } else {
                // Affichage des erreurs personnalisées
                foreach ($errors as $error) {
                    $this->addFlash('danger', $error);
                }
            }
        }
    
        return $this->render('liste_participation/frontend/addParticipation.html.twig', [
            'formParticipation' => $form->createView(),
            'offre' => $offre
        ]);
    }

    #[Route('app_back_participation', name: 'app_back_participation')]
public function listAllParticipationsBack(
    ListeParticipationRepository $repo, 
    Request $request, 
    PaginatorInterface $paginator
): Response {
    $searchTerm = $request->query->get('query');

    if ($searchTerm) {
        $query = $repo->createQueryBuilder('p')
            ->where('p.nom_p LIKE :s OR p.prenom_p LIKE :s')
            ->setParameter('s', '%'.$searchTerm.'%')
            ->getQuery();
    } else {
        $query = $repo->createQueryBuilder('p')
            ->orderBy('p.date_participation', 'DESC')
            ->getQuery();
    }

    // Ton métier de pagination ici
    $pagination = $paginator->paginate(
        $query,
        $request->query->getInt('page', 1),
        10
    );

    return $this->render('liste_participation/backend/BackListeParticipation.html.twig', [
        'participations' => $pagination, // C'est cette variable qui manquait dans Twig
    ]);
}
    
#[Route('/admin/participations/search', name: 'back_participation_search')]
public function searchBack(ListeParticipationRepository $repo, Request $request, PaginatorInterface $paginator): Response 
{
    $searchTerm = $request->query->get('query');
    
    $query = $repo->createQueryBuilder('p')
        ->where('p.nom_p LIKE :s OR p.prenom_p LIKE :s OR p.skills LIKE :s')
        ->setParameter('s', '%'.$searchTerm.'%')
        ->orderBy('p.date_participation', 'DESC')
        ->getQuery();

    $pagination = $paginator->paginate(
        $query, 
        $request->query->getInt('page', 1), 
        10
    );

    return $this->render('liste_participation/backend/BackListeParticipation.html.twig', [
        'participations' => $pagination, // Toujours utiliser le même nom pour le template
    ]);
}

    #[Route('/back/participation/delete/{id}', name: 'deleteBackParticipation', methods: ['POST', 'GET'])]
    public function deleteBackParticipation(int $id, EntityManagerInterface $em): Response
    {
        // 1. On récupère la participation par son ID
        $participation = $em->getRepository(ListeParticipation::class)->find($id);
    
        // 2. Si elle existe, on supprime
        if ($participation) {
            $em->remove($participation);
            $em->flush();
            $this->addFlash('success', 'Suppression réussie !');
        } else {
            $this->addFlash('error', 'Participation introuvable.');
        }
    
        // 3. REDIRECTION : On utilise le nom défini à la ligne 102 de ton code
        return $this->redirectToRoute('app_back_participation'); 
    }


}