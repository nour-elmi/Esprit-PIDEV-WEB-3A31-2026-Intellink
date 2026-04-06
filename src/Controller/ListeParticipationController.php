<?php

namespace App\Controller;

use App\Entity\ListeParticipation;
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
    

        $newParticipation = new ListeParticipation();
        $newParticipation->setDateParticipation(new \DateTime());
        //$newParticipation->setIdUser($user->getId());
        $newParticipation->setIdOffre($offre); 

        $form = $this->createForm(ListeFormType::class, $newParticipation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            //pdf
            $cvFile = $form->get('cv')->getData();

        if ($cvFile) {
            $newFilename = uniqid().'.'.$cvFile->guessExtension();

            try {
                $cvFile->move(
                    $this->getParameter('cv_directory'), // Dossier défini dans services.yaml
                    $newFilename
                );
            } catch (FileException $e) {
            }

            $newParticipation->setCv($newFilename);
        }


            $em->persist($newParticipation);
            $em->flush();
            
            return $this->redirectToRoute('showParticipation', ['id' => $id_offre]);
        }

        return $this->render('liste_participation/frontend/addParticipation.html.twig', [
            'formParticipation' => $form->createView(),
            'offre' => $offre
        ]);
    }
}