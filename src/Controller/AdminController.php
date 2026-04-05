<?php

namespace App\Controller;

use App\Repository\FormationRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class AdminController extends AbstractController
{
    #[Route('/formations', name: 'app_admin_formations')]
    public function formations(FormationRepository $repo): Response
    {
        return $this->render('admin/formations.html.twig', [
            'formations' => $repo->findAll(),
        ]);
    }

    #[Route('/participations/{id}', name: 'app_admin_participations')]
    public function participations(int $id, FormationRepository $formationRepo, ParticipationRepository $participationRepo): Response
    {
        $formation = $formationRepo->find($id);

        if (!$formation) {
            throw $this->createNotFoundException('Formation introuvable');
        }

        return $this->render('admin/participations.html.twig', [
            'formation' => $formation,
            'participations' => $participationRepo->findBy(['formation' => $formation]),
        ]);
    }

    #[Route('/participation/delete/{id}', name: 'app_admin_participation_delete')]
    public function deleteParticipation(int $id, ParticipationRepository $repo, FormationRepository $formationRepo, EntityManagerInterface $em): Response
    {
        $participation = $repo->find($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation introuvable');
        }

        $formationId = $participation->getFormation()->getIdFormation();

        $em->remove($participation);
        $em->flush();

        $this->addFlash('success', 'Participation supprimée avec succès !');
        return $this->redirectToRoute('app_admin_participations', ['id' => $formationId]);
    }

    
}