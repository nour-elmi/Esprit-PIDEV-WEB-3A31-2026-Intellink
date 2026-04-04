<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\OffreEmploiRepository;
use App\Entity\OffreEmploi;
//use App\Form\FormPlayerType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;

final class OffresEmploiController extends AbstractController
{
    #[Route('/showOffre', name:'showOffre')]
    public function listOffresfromDB(OffreEmploiRepository $repo)  // Changed BookRepository to BooksRepository
    {
        return $this->render('offres_emploi/frontend/showOffre.html.twig', ["list" => $repo->findAll()]);
    }

    #[Route('/deleteOffre/{id}', name:'deleteOffre')]
    public function deleteOffre($id, ManagerRegistry $Manager, OffreEmploiRepository $repo)
    {
        $em= $Manager->getManager();
        $newOffre= $repo->find($id);
        $em->remove($newOffre);
        $em->flush();
        return $this->redirectToRoute('showOffre');

    }
}
