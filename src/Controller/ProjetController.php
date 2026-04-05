<?php

namespace App\Controller;

use App\Entity\Projet;
use App\Entity\Collaboration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProjetController extends AbstractController
{
    #[Route('/projets', name: 'app_projet_index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $projets = $entityManager->getRepository(Projet::class)->findAll();

        return $this->render('projet/index.html.twig', [
            'projets' => $projets,
        ]);
    }

    #[Route('/projets/{id}', name: 'app_projet_show', requirements: ['id' => '\d+'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        $projet = $entityManager->getRepository(Projet::class)->find($id);

        if (!$projet) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $alreadyParticipated = $entityManager->getRepository(Collaboration::class)->findOneBy([
            'userId' => 1,
            'projet' => $projet,
        ]) !== null;

        return $this->render('projet/show.html.twig', [
            'projet' => $projet,
            'alreadyParticipated' => $alreadyParticipated,
        ]);
    }
}