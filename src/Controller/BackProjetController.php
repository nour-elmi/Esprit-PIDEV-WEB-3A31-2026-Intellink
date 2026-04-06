<?php

namespace App\Controller;

use App\Entity\Projet;
use App\Entity\Collaboration;
use App\Form\ProjetType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Utilisateur;

class BackProjetController extends AbstractController
{
    private const CREATEUR_EMAIL = 'chef@test.com';

    #[Route('/back/projets', name: 'app_back_projets', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $repo = $entityManager->getRepository(Projet::class);

        $search = trim((string) $request->query->get('search', ''));
        $statut = trim((string) $request->query->get('statut', ''));
        $sort = trim((string) $request->query->get('sort', 'recent'));

        $qb = $repo->createQueryBuilder('p')
            ->where('p.createur = :createur')
            ->setParameter('createur', self::CREATEUR_EMAIL);

        if ($search !== '') {
            $qb->andWhere('p.titre LIKE :search OR p.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($statut !== '' && $statut !== 'tous') {
            $qb->andWhere('p.statut = :statut')
               ->setParameter('statut', $statut);
        }

        if ($sort === 'ancien') {
            $qb->orderBy('p.id', 'ASC');
        } elseif ($sort === 'prix_asc') {
            $qb->orderBy('p.prix', 'ASC');
        } elseif ($sort === 'prix_desc') {
            $qb->orderBy('p.prix', 'DESC');
        } else {
            $qb->orderBy('p.id', 'DESC');
        }

        $projets = $qb->getQuery()->getResult();

        return $this->render('back/projets.html.twig', [
            'projets' => $projets,
            'search' => $search,
            'statut' => $statut,
            'sort' => $sort,
        ]);
    }

    #[Route('/back/projets/ajouter', name: 'app_back_projet_add', methods: ['GET', 'POST'])]
    public function add(Request $request, EntityManagerInterface $entityManager): Response
    {
        $projet = new Projet();
        $projet->setStatut('en attente');

        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $projet->setCreateur(self::CREATEUR_EMAIL);

            if ($projet->getDateCreation() === null) {
                $projet->setDateCreation(new \DateTimeImmutable());
            }

            $entityManager->persist($projet);
            $entityManager->flush();

            $this->addFlash('success', 'Projet ajouté avec succès.');

            return $this->redirectToRoute('app_back_projets');
        }

        return $this->render('back/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Ajouter un projet',
            'subtitle' => 'Créez un nouveau projet avec une présentation claire et professionnelle.',
            'button_label' => 'Créer le projet',
            'is_edit' => false,
        ]);
    }

    #[Route('/back/projets/{id}/modifier', name: 'app_back_projet_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $projet = $entityManager->getRepository(Projet::class)->find($id);

        if (!$projet || $projet->getCreateur() !== self::CREATEUR_EMAIL) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $projet->setCreateur(self::CREATEUR_EMAIL);
            $entityManager->flush();

            $this->addFlash('success', 'Projet modifié avec succès.');

            return $this->redirectToRoute('app_back_projets');
        }

        return $this->render('back/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Modifier le projet',
            'subtitle' => 'Mettez à jour les informations du projet.',
            'button_label' => 'Enregistrer les modifications',
            'is_edit' => true,
        ]);
    }

    #[Route('/back/projets/{id}/supprimer', name: 'app_back_projet_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $projet = $entityManager->getRepository(Projet::class)->find($id);

        if (!$projet || $projet->getCreateur() !== self::CREATEUR_EMAIL) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_projet_' . $projet->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_back_projets');
        }

        $entityManager->remove($projet);
        $entityManager->flush();

        $this->addFlash('success', 'Projet supprimé avec succès.');

        return $this->redirectToRoute('app_back_projets');
    }

   #[Route('/back/projets/{id}/demandes', name: 'app_back_projet_demandes', requirements: ['id' => '\d+'], methods: ['GET'])]
public function demandes(
    int $id,
    EntityManagerInterface $entityManager
): Response {
    $projet = $entityManager->getRepository(Projet::class)->find($id);

    if (!$projet || $projet->getCreateur() !== self::CREATEUR_EMAIL) {
        throw $this->createNotFoundException('Projet introuvable.');
    }

    $demandes = $entityManager->getRepository(Collaboration::class)->findBy(
        ['projet' => $projet],
        ['id' => 'DESC']
    );

    $userIds = array_values(array_unique(array_filter(array_map(
        static fn (Collaboration $demande) => $demande->getUserId(),
        $demandes
    ))));

    $utilisateursParId = [];

    if (!empty($userIds)) {
        $utilisateurs = $entityManager->getRepository(Utilisateur::class)->findBy([
            'id' => $userIds
        ]);

        foreach ($utilisateurs as $utilisateur) {
            $utilisateursParId[$utilisateur->getId()] = $utilisateur;
        }
    }

    return $this->render('back/demandes.html.twig', [
        'projet' => $projet,
        'demandes' => $demandes,
        'utilisateursParId' => $utilisateursParId,
    ]);
}

    #[Route('/back/demande/{id}/accept', name: 'app_back_demande_accept', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function accepter(
        int $id,
        EntityManagerInterface $entityManager
    ): Response {
        $collaboration = $entityManager->getRepository(Collaboration::class)->find($id);

        if (!$collaboration) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $projet = $collaboration->getProjet();

        if (!$projet || $projet->getCreateur() !== self::CREATEUR_EMAIL) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $collaboration->setEtat('ACCEPTEE');
        $entityManager->flush();

        $this->addFlash('success', 'La demande a été acceptée.');

        return $this->redirectToRoute('app_back_projet_demandes', [
            'id' => $projet->getId(),
        ]);
    }

    #[Route('/back/demande/{id}/refuse', name: 'app_back_demande_refuse', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function refuser(
        int $id,
        EntityManagerInterface $entityManager
    ): Response {
        $collaboration = $entityManager->getRepository(Collaboration::class)->find($id);

        if (!$collaboration) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $projet = $collaboration->getProjet();

        if (!$projet || $projet->getCreateur() !== self::CREATEUR_EMAIL) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $collaboration->setEtat('REFUSEE');
        $entityManager->flush();

        $this->addFlash('success', 'La demande a été refusée.');

        return $this->redirectToRoute('app_back_projet_demandes', [
            'id' => $projet->getId(),
        ]);
    }
}