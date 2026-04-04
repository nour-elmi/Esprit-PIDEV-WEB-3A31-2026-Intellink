<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListeParticipationController extends AbstractController
{
    #[Route('/liste/participation', name: 'app_liste_participation')]
    public function index(): Response
    {
        return $this->render('liste_participation/index.html.twig', [
            'controller_name' => 'ListeParticipationController',
        ]);
    }
}
