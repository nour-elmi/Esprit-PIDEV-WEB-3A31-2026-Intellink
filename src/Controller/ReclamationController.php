<?php

namespace App\Controller;

use App\Entity\Reclamation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/reclamation')]
class ReclamationController extends AbstractController
{
    // ==========================================
    // 1. LISTE DES RÉCLAMATIONS
    // ==========================================
    #[Route('/', name: 'app_reclamation_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }

        // CORRECTION ICI : On utilise 'utilisateur' et 'date_creation' pour correspondre à votre entité
        $reclamations = $entityManager->getRepository(Reclamation::class)->findBy(
            ['utilisateur' => $user], 
            ['date_creation' => 'DESC']
        );

        return $this->render('frontUser/reclamation/index.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    // ==========================================
    // 2. CRÉATION
    // ==========================================
    #[Route('/new', name: 'app_reclamation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }

        if ($request->isMethod('POST')) {
            $reclamation = new Reclamation();
            
            // CORRECTION ICI : setUtilisateur au lieu de setUser
            $reclamation->setUtilisateur($user);
            
            $reclamation->setObjet($request->request->get('objet'));
            $reclamation->setType($request->request->get('type'));
            $reclamation->setPriorite($request->request->get('priorite'));
            $reclamation->setDescription($request->request->get('description'));
            $reclamation->setStatut('OUVERT');
            $reclamation->setDateCreation(new \DateTime());

            $file = $request->files->get('pieceJointe');
            if ($file) {
                $newFilename = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid().'.'.$file->guessExtension();
                $file->move($this->getParameter('reclamations_directory'), $newFilename);
                $reclamation->setPieceJointe($newFilename);
            }

            $entityManager->persist($reclamation);
            $entityManager->flush();

            $this->addFlash('success', 'Votre réclamation a été envoyée avec succès.');
            return $this->redirectToRoute('app_reclamation_index');
        }

        return $this->render('frontUser/reclamation/new.html.twig');
    }

    // ==========================================
    // 3. CONSULTATION & MODIFICATION
    // ==========================================
    #[Route('/{id}', name: 'app_reclamation_show', methods: ['GET', 'POST'])]
    public function show(Reclamation $reclamation, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // CORRECTION ICI : getUtilisateur() au lieu de getUser()
        if ($reclamation->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if ($request->isMethod('POST') && $reclamation->getStatut() === 'OUVERT') {
            $reclamation->setObjet($request->request->get('objet'));
            $reclamation->setType($request->request->get('type'));
            $reclamation->setPriorite($request->request->get('priorite'));
            $reclamation->setDescription($request->request->get('description'));

            $file = $request->files->get('pieceJointe');
            if ($file) {
                $newFilename = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid().'.'.$file->guessExtension();
                $file->move($this->getParameter('reclamations_directory'), $newFilename);
                $reclamation->setPieceJointe($newFilename);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Votre réclamation a été modifiée avec succès.');
            return $this->redirectToRoute('app_reclamation_show', ['id' => $reclamation->getId()]);
        }

        return $this->render('frontUser/reclamation/show.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    // ==========================================
    // 4. SUPPRESSION
    // ==========================================
    #[Route('/{id}/delete', name: 'app_reclamation_delete', methods: ['POST'])]
    public function delete(Reclamation $reclamation, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($reclamation->getStatut() === 'OUVERT' && $this->isCsrfTokenValid('delete'.$reclamation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($reclamation);
            $entityManager->flush();
            $this->addFlash('success', 'Réclamation supprimée définitivement.');
        }
        return $this->redirectToRoute('app_reclamation_index');
    }
}