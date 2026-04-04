<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
    #[Route('/index', name: 'index')] // C'est l'URL que vous taperez dans le navigateur
    public function index(): Response
    {
        // On demande à Symfony d'afficher le fichier situé dans templates/index.html.twig
        return $this->render('index.html.twig');
    }
}