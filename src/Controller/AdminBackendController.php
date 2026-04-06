<?php

namespace App\Controller;

use App\Entity\Utilisateur; // ✅ CORRIGÉ
use App\Entity\Reclamation;
use App\Repository\UtilisateurRepository; // ✅ CORRIGÉ
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminBackendController extends AbstractController
{
    /* =========================================================================
       MODULE 1 : GESTION DES UTILISATEURS
       ========================================================================= */
    #[Route('/utilisateurs', name: 'app_admin_users')]
    public function listUsers(UtilisateurRepository $userRepo, Request $request): Response // ✅ CORRIGÉ
    {
        $query = $request->query->get('search');
        
        if ($query) {
            // Recherche fonctionnelle directement via le QueryBuilder
            $users = $userRepo->createQueryBuilder('u')
                ->where('u.nom LIKE :query OR u.email LIKE :query')
                ->setParameter('query', '%' . $query . '%')
                ->getQuery()
                ->getResult();
        } else {
            $users = $userRepo->findAll();
        }

        return $this->render('backUser/listUser.html.twig', [
            'users' => $users,
            'searchQuery' => $query
        ]);
    }

    #[Route('/utilisateurs/{id}', name: 'app_admin_user_detail')]
    public function userDetail(Utilisateur $user): Response // ✅ CORRIGÉ
    {
        return $this->render('backUser/detailUser.html.twig', [
            'user' => $user
        ]);
    }

    #[Route('/utilisateurs/{id}/statut/{statut}', name: 'app_admin_user_status', methods: ['POST'])]
    public function changeUserStatus(Utilisateur $user, string $statut, EntityManagerInterface $em, Request $request): Response // ✅ CORRIGÉ
    {
        if ($this->isCsrfTokenValid('status'.$user->getId(), $request->request->get('_token'))) {
            $user->setStatutCompte($statut);
            $em->flush();
            $this->addFlash('success', "Le statut de l'utilisateur a été mis à jour.");
        }
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/utilisateurs/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(Utilisateur $user, EntityManagerInterface $em, Request $request): Response // ✅ CORRIGÉ
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', "L'utilisateur a été supprimé.");
        }
        return $this->redirectToRoute('app_admin_users');
    }

    /* =========================================================================
       MODULE 2 : GESTION DES RÉCLAMATIONS
       ========================================================================= */
   #[Route('/reclamations', name: 'app_admin_reclamations')]
    public function listReclamations(ReclamationRepository $recRepo, Request $request): Response
    {
        $userId = $request->query->get('user_id');
        $statut = $request->query->get('statut');

        // 1. Récupération classique
        if ($userId) {
            $reclamations = $recRepo->findBy(['utilisateur' => $userId]); 
        } elseif ($statut && $statut !== 'TOUT') {
            $reclamations = $recRepo->findBy(['statut' => $statut]);
        } else {
            $reclamations = $recRepo->findAll();
        }

        // 2. NOUVEAU : Logique de regroupement par Email
        $groupedReclamations = [];
        foreach ($reclamations as $r) {
            // On récupère l'email (ou un texte par défaut si l'utilisateur a été supprimé)
            $email = $r->getUtilisateur() ? $r->getUtilisateur()->getEmail() : 'Utilisateurs supprimés';
            
            // On range la réclamation dans la "boîte" de cet email
            $groupedReclamations[$email][] = $r;
        }

        // 3. On envoie le tableau groupé à Twig
        return $this->render('backReclamation/listReclamation.html.twig', [
            'groupedReclamations' => $groupedReclamations, // ✅ Le nouveau tableau
            'currentUserId' => $userId,
            'currentStatut' => $statut
        ]);
    }
    // =========================================================================
    //  TRAITEMENT D'UNE RÉCLAMATION (La fameuse route manquante !)
    // =========================================================================
    #[Route('/reclamations/{id}/traiter', name: 'app_admin_reclamation_treat', methods: ['GET', 'POST'])]
    public function treatReclamation(Reclamation $reclamation, Request $request, EntityManagerInterface $em): Response
    {
        // Si l'administrateur a cliqué sur "Envoyer la réponse" (Requête POST)
        if ($request->isMethod('POST')) {
            $statut = $request->request->get('statut');
            $reponse = $request->request->get('reponse');

            if ($statut && $reponse) {
                $reclamation->setStatut($statut);
                $reclamation->setReponseAdmin($reponse); // Assure-toi que ton entité a bien ce setter
                $em->flush();
                
                $this->addFlash('success', "La réclamation a été traitée avec succès !");
                return $this->redirectToRoute('app_admin_reclamations');
            } else {
                $this->addFlash('error', "Veuillez remplir le statut et la réponse.");
            }
        }

        // Sinon, on affiche juste la page avec le formulaire (Requête GET)
        return $this->render('backReclamation/treatReclamation.html.twig', [
            'reclamation' => $reclamation
        ]);
    }
}