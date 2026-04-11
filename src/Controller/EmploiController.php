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
    public function listOffresfromDB(EmploiRepository $repo, Request $request)
    {
        $searchTerm = $request->query->get('search');
        $offres = $repo->searchByTerm($searchTerm);
        return $this->render('emploi/front/showOffre.html.twig', ['offre' => $offres,
            'searchTerm' => $searchTerm ]);
    }

    #[Route('/showoffreRecruteur', name: 'showoffreRecruteur')]
    public function listOffresRfromDB(EmploiRepository $repo, Request $request)
    {
        $searchTerm = $request->query->get('search');
        $offres = $repo->searchByTerm($searchTerm);
        return $this->render('emploi/front/showOffreR.html.twig', [
            'offre' => $offres,
            'searchTerm' => $searchTerm 
        ]);
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

    #[Route('/deleteOffre/{id}', name:'deleteOffre')]
    public function deleteOffre($id, ManagerRegistry $Manager, EmploiRepository $repo)
    {
        $em= $Manager->getManager();
        $newOffre= $repo->find($id);
        $em->remove($newOffre);
        $em->flush();
        return $this->redirectToRoute('showoffreRecruteur');
    }

    #[Route('/updateOffre/{id}', name:'updateOffre')]
    public function updateOffre($id, ManagerRegistry $Manager, EmploiRepository $repo, Request $request)
    {
        $em = $Manager->getManager();
        $offre = $repo->find($id);
        if (!$offre) {
            throw $this->createNotFoundException("L'offre avec l'ID $id n'existe pas.");
        }
        $form = $this->createForm(EmploiType::class, $offre);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush(); 
            return $this->redirectToRoute('showoffreRecruteur');
        }
        return $this->render('emploi/front/addOffre.html.twig', [
            'formOffre' => $form->createView()
        ]);
    }
}
