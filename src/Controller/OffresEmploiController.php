<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\OffreEmploiRepository;
use App\Entity\OffreEmploi;
use App\Form\OffreFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;

final class OffresEmploiController extends AbstractController
{
    #[Route('/showOffre', name:'showOffre')]
    public function listOffresfromDB(OffreEmploiRepository $repo)  
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

    #[Route('/updateOffre/{id}', name:'updateOffre')]
    public function updateOffre($id, ManagerRegistry $Manager, OffreEmploiRepository $repo, Request $request)
    {
        $em = $Manager->getManager();
        $offre = $repo->find($id); 
        
        $form = $this->createForm(OffreFormType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush(); 
            return $this->redirectToRoute('showOffre'); 
        }

        return $this->render('offres_emploi/frontend/updateOffre.html.twig', [
            'formOffre' => $form->createView(),
            'offre' => $offre
        ]);
    }

    #[Route('/addOffre', name:'addOffre')]
    public function addOffre(ManagerRegistry $Manager, Request $request)
    {
        $em = $Manager->getManager();
        $newOffre= new OffreEmploi();
        $form= $this->createForm(OffreFormType::class, $newOffre);
        $form->handleRequest($request);
        if($form->isSubmitted())
        {
            $newOffre->setIdUser(1);
            $em->persist($newOffre);
            $em->flush();
            return $this->redirectToRoute('showOffre');
        }
        $em->flush();
        return $this->render('offres_emploi/frontend/addOffre.html.twig', ['formOffre' => $form]);

    }
}
