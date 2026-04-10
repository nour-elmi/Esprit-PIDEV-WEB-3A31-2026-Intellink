<?php

namespace App\Controller;

use App\Entity\Projet;
use App\Entity\Collaboration;
use App\Form\DemandeParticipationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ParticipationController extends AbstractController
{
    #[Route('/participer/{id}', name: 'app_participation_add', requirements: ['id' => '\d+'])]
    public function add(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $projet = $entityManager->getRepository(Projet::class)->find($id);

        if (!$projet) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $user = $this->getUser();

        if (!$user || !method_exists($user, 'getId') || $user->getId() === null) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour participer à un projet.');
        }

        $demande = new Collaboration();
        $demande->setProjet($projet);
        $demande->setUserId($user->getId());
        $demande->setEtat('EN_ATTENTE');
        $demande->setDateCreation(new \DateTime());

        $form = $this->createForm(DemandeParticipationType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $roleChoisi = $form->get('roleSouhaite')->getData();
            $autreRole = trim((string) $form->get('autreRole')->getData());

            $disponibiliteChoisie = $form->get('disponibilite')->getData();
            $autreDisponibilite = trim((string) $form->get('autreDisponibilite')->getData());

            if ($roleChoisi === '__autre__') {
                $demande->setRoleSouhaite($autreRole !== '' ? $autreRole : null);
            } else {
                $demande->setRoleSouhaite($roleChoisi);
            }

            if ($disponibiliteChoisie === '__autre__') {
                $demande->setDisponibilite($autreDisponibilite !== '' ? $autreDisponibilite : null);
            } else {
                $demande->setDisponibilite($disponibiliteChoisie);
            }

            $entityManager->persist($demande);
            $entityManager->flush();

            $this->addFlash('success', 'Votre demande a été envoyée avec succès.');

            return $this->redirectToRoute('app_mes_demandes');
        }

        return $this->render('participation/form.html.twig', [
            'form' => $form->createView(),
            'projet' => $projet,
            'editMode' => false,
        ]);
    }

    #[Route('/mes-demandes', name: 'app_mes_demandes')]
    public function mesDemandes(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user || !method_exists($user, 'getId') || $user->getId() === null) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour voir vos demandes.');
        }

        $demandes = $entityManager->getRepository(Collaboration::class)->findBy([
            'userId' => $user->getId()
        ]);

        return $this->render('participation/mes_demandes.html.twig', [
            'demandes' => $demandes,
        ]);
    }

    #[Route('/participation/{id}/modifier', name: 'app_participation_edit', requirements: ['id' => '\d+'])]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $demande = $entityManager->getRepository(Collaboration::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $user = $this->getUser();

        if (!$user || !method_exists($user, 'getId') || $user->getId() === null) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour modifier une demande.');
        }

        if ($demande->getUserId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres demandes.');
        }

        if ($demande->getEtat() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Seules les demandes en attente peuvent être modifiées.');
            return $this->redirectToRoute('app_mes_demandes');
        }

        $form = $this->createForm(DemandeParticipationType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $roleChoisi = $form->get('roleSouhaite')->getData();
            $autreRole = trim((string) $form->get('autreRole')->getData());

            $disponibiliteChoisie = $form->get('disponibilite')->getData();
            $autreDisponibilite = trim((string) $form->get('autreDisponibilite')->getData());

            if ($roleChoisi === '__autre__') {
                $demande->setRoleSouhaite($autreRole !== '' ? $autreRole : null);
            } else {
                $demande->setRoleSouhaite($roleChoisi);
            }

            if ($disponibiliteChoisie === '__autre__') {
                $demande->setDisponibilite($autreDisponibilite !== '' ? $autreDisponibilite : null);
            } else {
                $demande->setDisponibilite($disponibiliteChoisie);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Votre demande a été modifiée avec succès.');

            return $this->redirectToRoute('app_mes_demandes');
        }

        return $this->render('participation/form.html.twig', [
            'form' => $form->createView(),
            'projet' => $demande->getProjet(),
            'editMode' => true,
        ]);
    }

    #[Route('/participation/{id}/supprimer', name: 'app_participation_delete', requirements: ['id' => '\d+'])]
    public function delete(
        int $id,
        EntityManagerInterface $entityManager
    ): Response {
        $demande = $entityManager->getRepository(Collaboration::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $user = $this->getUser();

        if (!$user || !method_exists($user, 'getId') || $user->getId() === null) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour supprimer une demande.');
        }

        if ($demande->getUserId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez supprimer que vos propres demandes.');
        }

        if ($demande->getEtat() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Seules les demandes en attente peuvent être supprimées.');
            return $this->redirectToRoute('app_mes_demandes');
        }

        $entityManager->remove($demande);
        $entityManager->flush();

        $this->addFlash('success', 'Votre demande a été supprimée.');

        return $this->redirectToRoute('app_mes_demandes');
    }
}