<?php

namespace App\Controller;

use App\Entity\ListeParticipation;
use App\Entity\OffreEmploi;
use App\Form\ListeFormType; // Assurez-vous que ce formulaire existe
use App\Repository\ListeParticipationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\OffreEmploiRepository;

final class ListeParticipationController extends AbstractController
{
    #[Route('/showParticipation/{id}', name: 'showParticipation')]
    public function showParticipations(OffreEmploi $offre): Response
    {
        return $this->render('liste_participation/frontend/showParticipation.html.twig', [
            'offre' => $offre,
            'participations' => $offre->getParticipations(),
        ]);
    }

    #[Route('/showParticipationUser', name: 'showParticipationUser')]
    public function listParticipationsUfromDB(ListeParticipationRepository $repo): Response
    {
        return $this->render('liste_participation/frontend/showParticipationUser.html.twig', [
            "list" => $repo->findAll()
        ]);
    }

    #[Route('/deleteParticipation/{id}', name: 'deleteParticipation')]
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
        $newParticipation->setIdUser(1); 
        $newParticipation->setIdOffre($offre); 

        $form = $this->createForm(ListeFormType::class, $newParticipation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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