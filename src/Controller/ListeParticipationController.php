<?php

namespace App\Controller;

use App\Entity\ListeParticipation;
use App\Entity\Emploi;
use App\Form\ListeParticipationType;
use App\Repository\EmploiRepository;
use App\Repository\ListeParticipationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListeParticipationController extends AbstractController
{
    #[Route('/showListe/{id_offre}', name: 'showListe', defaults: ['id_offre' => null])]
    public function showListe(?Emploi $offre, ListeParticipationRepository $repo): Response
    {
        // Si un ID est passé, on filtre par offre, sinon on affiche tout
        $participations = $offre 
            ? $repo->findBy(['id_offre' => $offre]) 
            : $repo->findAll();

        return $this->render('emploi/front/showListe.html.twig', [
            'lesParticipations' => $participations,
            'offreChoisie' => $offre // Pour afficher le titre de l'offre en haut si besoin
        ]);
    }

    #[Route('/showListeBack/{id}', name: 'showListeBack', defaults: ['id' => null])]
    public function showListeBack(?Emploi $offre, ListeParticipationRepository $repo): Response
    {
        // Symfony va maintenant lier automatiquement {id} à l'objet Emploi $offre
        $participations = $offre 
            ? $repo->findBy(['id_offre' => $offre]) 
            : $repo->findAll();

        return $this->render('emploi/back/ListeBack.html.twig', [
            'lesParticipations' => $participations,
            'offreChoisie' => $offre
        ]);
    }

    // On passe l'id_offre dans l'URL pour savoir pour quel job on postule
    #[Route('/addListe/{id_offre}', name: 'addListe')]
    public function addListe(
        int $id_offre, 
        ManagerRegistry $doctrine, 
        Request $request, 
        EmploiRepository $emploiRepo
    ): Response {
        $em = $doctrine->getManager();
        
        // 1. On récupère l'entité Emploi
        $offre = $emploiRepo->find($id_offre);

        if (!$offre) {
            throw $this->createNotFoundException("L'offre n'existe pas.");
        }

        $participation = new ListeParticipation();
        
        // 2. ON ASSOCIE L'OFFRE AUTOMATIQUEMENT
        $participation->setIdOffre($offre);
        $participation->setDateParticipation(new \DateTime()); // Date du jour
        $participation->setIdUser(1); // À dynamiser plus tard

        $form = $this->createForm(ListeParticipationType::class, $participation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $participation->setDateParticipation(new \DateTime());
            $em->persist($participation);
            $em->flush();

            return $this->redirectToRoute('showListe');
        }

        return $this->render('emploi/front/addListe.html.twig', [
            'formOffre' => $form->createView(),
            'offre' => $offre
        ]);
    }
}