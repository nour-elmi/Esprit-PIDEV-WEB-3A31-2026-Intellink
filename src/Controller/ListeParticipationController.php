<?php

namespace App\Controller;

use App\Entity\ListeParticipation;
use App\Entity\Emploi;
use App\Form\ListeParticipationType;
use App\Repository\EmploiRepository;
use App\Repository\ListeParticipationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ListeParticipationController extends AbstractController
{
    #[Route('/showListe/{id_offre}', name: 'showListe', defaults: ['id_offre' => null])]
    public function showListe(?Emploi $offre, ListeParticipationRepository $repo): Response
    {
        // Si un ID est passé, on filtre par offre, sinon on affiche tout
        $participations = $offre 
            ? $repo->findBy(['id_offre' => $offre]) 
            : $repo->findAll();

        return $this->render('emploi/front/showListe.html.twig', [
            'lesParticipations' => $participations,
            'offreChoisie' => $offre // Pour afficher le titre de l'offre en haut si besoin
        ]);
    }

    #[Route('/showListeBack/{id}', name: 'showListeBack', defaults: ['id' => null])]
    public function showListeBack(?Emploi $offre, ListeParticipationRepository $repo): Response
    {
        // Symfony va maintenant lier automatiquement {id} à l'objet Emploi $offre
        $participations = $offre 
            ? $repo->findBy(['id_offre' => $offre]) 
            : $repo->findAll();

        return $this->render('emploi/back/ListeBack.html.twig', [
            'lesParticipations' => $participations,
            'offreChoisie' => $offre
        ]);
    }

    // On passe l'id_offre dans l'URL pour savoir pour quel job on postule
    #[Route('/addListe/{id_offre}', name: 'addListe')]
    public function addListe(int $id_offre, ManagerRegistry $doctrine, Request $request, EmploiRepository $emploiRepo): Response {
        $em = $doctrine->getManager();
        $offre = $emploiRepo->find($id_offre);

        if (!$offre) {
            throw $this->createNotFoundException("L'offre n'existe pas.");
        }

        $participation = new ListeParticipation();
        $participation->setIdOffre($offre);
        $participation->setDateParticipation(new \DateTime());
        $participation->setIdUser(1); // À dynamiser avec $this->getUser() plus tard

        $form = $this->createForm(ListeParticipationType::class, $participation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // 1. On récupère le fichier uploadé via le champ 'cv'
            /** @var UploadedFile $cvFile */
            $cvFile = $form->get('cv')->getData();

            if ($cvFile) {
                // 2. On génère un nom unique : "cv-idunique.pdf"
                $newFilename = 'cv-' . uniqid() . '.' . $cvFile->guessExtension();

                // 3. On déplace le fichier vers le dossier de destination
                try {
                    $cvFile->move(
                        $this->getParameter('cv_directory'), // Ce paramètre doit être défini dans services.yaml
                        $newFilename
                    );
                    
                    // 4. On enregistre le NOM du fichier en base de données
                    $participation->setCv($newFilename);
                    
                } catch (FileException $e) {
                    // Optionnel : ajouter un message flash d'erreur si l'upload échoue
                    $this->addFlash('error', 'Impossible d\'enregistrer le CV.');
                }
            }

            $participation->setDateParticipation(new \DateTime());
            $em->persist($participation);
            $em->flush();

            return $this->redirectToRoute('showListe');
        }

        return $this->render('emploi/front/addListe.html.twig', [
            'formOffre' => $form->createView(),
            'offre' => $offre
        ]);
    }

    #[Route('/deleteListe/{id}', name:'deleteListe')]
    public function deleteListe($id, ManagerRegistry $Manager, ListeParticipationRepository $repo)
    {
        $em= $Manager->getManager();
        $Liste= $repo->find($id);
        $em->remove($Liste);
        $em->flush();
        return $this->redirectToRoute('showListeBack');
    }

    #[Route('/updateStatut/{id}/{nouveauStatut}', name: 'updateStatut')]
    public function updateStatut(int $id, string $nouveauStatut, ListeParticipationRepository $repo, EntityManagerInterface $em): Response
    {
        $p = $repo->find($id);
        
        try {
            // Cela va crash si $nouveauStatut n'est pas 'aceptee', 'refusee' ou 'en_attente'
            $enumValue = \App\Enum\stat::from($nouveauStatut);
            $p->setStatut($enumValue);
            
            $em->flush();
            $this->addFlash('success', 'Statut mis à jour !');
        } catch (\ValueError $e) {
            $this->addFlash('error', 'Valeur de statut invalide : ' . $nouveauStatut);
        }

        $response = $this->redirectToRoute('showListeBack', ['id' => $p->getIdOffre()->getId()]);
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        return $response;
    }
}