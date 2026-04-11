<?php

namespace App\Controller;

use App\Entity\Emploi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\EmploiRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use App\Form\EmploiType;



final class EmploiController extends AbstractController
{
    #[Route('/showoffre', name: 'showoffre')]
    public function listOffresfromDB(EmploiRepository $repo)
    {
        return $this->render('emploi/front/showOffre.html.twig', ["offre" => $repo->findAll()]);
    }

    #[Route('/showoffreRecruteur', name: 'showoffreRecruteur')]
    public function listOffresRfromDB(EmploiRepository $repo)
    {
        return $this->render('emploi/front/showOffreR.html.twig', ["offre" => $repo->findAll()]);
    }

    #[Route('/addOffre', name:'addOffre')]
    public function addOffre(ManagerRegistry $Manager, Request $request)
    {
        $em = $Manager->getManager();
        $newOffre= new Emploi();
        $form= $this->createForm(EmploiType::class, $newOffre);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $newOffre->setIdUser(1); 
            $em->persist($newOffre);
            $em->flush();
            return $this->redirectToRoute('showoffreRecruteur');
        }
        $em->flush();
        return $this->render('emploi/front/addOffre.html.twig', ['formOffre' => $form]);
    }
}
