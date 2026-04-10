<?php

namespace App\Controller;

use App\Entity\Projet;
use App\Entity\Collaboration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AdminProjetController extends AbstractController
{
    public function index(EntityManagerInterface $entityManager): Response
    {
        $projets = $entityManager->getRepository(Projet::class)->findBy([], [
            'id' => 'DESC'
        ]);

        $projetsAvecStats = [];

        foreach ($projets as $projet) {
            $nbParticipations = $entityManager->getRepository(Collaboration::class)->count([
                'projet' => $projet
            ]);

            $projetsAvecStats[] = [
                'projet' => $projet,
                'nbParticipations' => $nbParticipations,
            ];
        }

        return $this->render('admin/projets.html.twig', [
            'projetsAvecStats' => $projetsAvecStats,
        ]);
    }

    public function participations(int $id, EntityManagerInterface $entityManager): Response
    {
        $projet = $entityManager->getRepository(Projet::class)->find($id);

        if (!$projet) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $participations = $entityManager->getRepository(Collaboration::class)->findBy(
            ['projet' => $projet],
            ['id' => 'DESC']
        );

        return $this->render('admin/participations.html.twig', [
            'projet' => $projet,
            'participations' => $participations,
        ]);
    }

    public function delete(int $id, EntityManagerInterface $entityManager): RedirectResponse
    {
        $projet = $entityManager->getRepository(Projet::class)->find($id);

        if (!$projet) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $entityManager->remove($projet);
        $entityManager->flush();

        $this->addFlash('success', 'Projet supprimé avec succès.');

        return $this->redirectToRoute('admin_projets');
    }
}