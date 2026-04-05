<?php

namespace App\Controller;

use App\Entity\ListeParticipation;
use App\Form\ListeFormType; // Assurez-vous que ce formulaire existe
use App\Repository\ListeParticipationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListeParticipationController extends AbstractController
{
    #[Route('/showParticipation', name: 'showParticipation')]
    public function listParticipationsfromDB(ListeParticipationRepository $repo): Response
    {
        return $this->render('liste_participation/frontend/showParticipation.html.twig', [
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

    #[Route('/addParticipation', name: 'addParticipation')]
    public function addParticipation(ManagerRegistry $Manager, Request $request): Response
    {
        $em = $Manager->getManager();
        $newParticipation = new ListeParticipation();
        
        // Initialisation des valeurs par défaut pour Intellink
        $newParticipation->setDateParticipation(new \DateTime());
        $newParticipation->setIdUser(1); // Statique comme demandé pour le moment

        $form = $this->createForm(ListeFormType::class, $newParticipation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($newParticipation);
            $em->flush();
            return $this->redirectToRoute('showParticipation');
        }

        return $this->render('liste_participation/frontend/addParticipation.html.twig', [
            'formParticipation' => $form->createView()
        ]);
    }
}