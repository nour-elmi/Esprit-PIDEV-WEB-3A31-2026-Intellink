<?php

namespace App\Controller;

use App\Entity\ListeParticipation;
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
    #[Route('/showListe', name: 'showListe')]
    public function showListe(ListeParticipationRepository $repo): Response
    {
        return $this->render('emploi/front/showListe.html.twig', [
            'lesParticipations' => $repo->findAll(),
            'offre' => $repo->findAll(), // Pour ton count dans le template
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