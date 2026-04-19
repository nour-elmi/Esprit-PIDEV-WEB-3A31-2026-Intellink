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
                // 2. NOUVEAU : Logique de regroupement par Email
        $groupedReclamations = [];
        foreach ($reclamations as $r) {
            $email = 'Utilisateurs supprimés';
            try {
                $u = $r->getUtilisateur();
                if ($u) {
                    $email = $u->getEmail();
                }
            } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                $email = 'Utilisateurs supprimés';
            }
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
    public function treatReclamation(Reclamation $reclamation, Request $request, EntityManagerInterface $em, \Symfony\Component\Mailer\MailerInterface $mailer): Response
    {
        // Si l'administrateur a cliqué sur "Envoyer la réponse" (Requête POST)
        if ($request->isMethod('POST')) {
            $statut = $request->request->get('statut');
            $reponse = $request->request->get('reponse');

            if ($statut && $reponse) {
                                                $reclamation->setStatut($statut);
                $reclamation->setReponseAdmin($reponse);

                // --- MAILING & PUSHER ---
                $user = $reclamation->getUtilisateur();
                if ($user) {
                    // PUSHER
                    try {
                        $pusher = new \Pusher\Pusher(
                            $_ENV["PUSHER_KEY"], 
                            $_ENV["PUSHER_SECRET"], 
                            $_ENV["PUSHER_APP_ID"],
                            [
                                "cluster" => $_ENV["PUSHER_CLUSTER"], 
                                "useTLS" => true, 
                                "curl_options" => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]
                            ]
                        );
                        
                        $payloadToUser = [
                            "reclamationId" => $reclamation->getId(),
                            "objet" => substr($reclamation->getObjet(), 0, 15),
                            "message" => "Votre réclamation \"" . substr($reclamation->getObjet(), 0, 20) . "...\" a été traitée."
                        ];
                        
                        $pusher->trigger("user-channel-" . $user->getId(), "reclamation-treated", $payloadToUser);
                    } catch (\Throwable $e) {}

                    // EMAIL
                    if ($user->getEmail()) {
                        try {
                            $emailMessage = (new \Symfony\Component\Mime\Email())
                                ->from('no-reply@intellink.com')
                                ->to($user->getEmail())
                                ->subject('Mise à jour de votre réclamation - IntelLink')
                                ->html(
                                    '<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">' .
                                        '<div style="background-color: #2F80ED; padding: 20px; text-align: center;">' .
                                            '<h2 style="color: #fff; margin: 0;">Mise à jour de votre ticket</h2>' .
                                        '</div>' .
                                        '<div style="padding: 20px; line-height: 1.6;">' .
                                            '<p>Bonjour <strong>' . htmlspecialchars($user->getNom()) . '</strong>,</p>' .
                                            '<p>Votre réclamation "<strong>' . htmlspecialchars($reclamation->getObjet()) . '</strong>" a été mise à jour par un administrateur.</p>' .
                                            '<p><strong>Nouveau statut :</strong> <span style="background: ' . ($statut === 'TRAITE' ? '#10b981' : '#f39c12') . '; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">' . $statut . '</span></p>' .
                                            '<div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid #2F80ED; margin: 20px 0;">' .
                                                '<strong style="color: #2F80ED;">Réponse de l\'administration :</strong><br>' .
                                                nl2br(htmlspecialchars($reponse)) .
                                            '</div>' .
                                            '<p>Vous pouvez consulter les détails complets depuis votre espace client.</p>' .
                                            '<p>Cordialement,<br><em>L\'équipe IntelLink</em></p>' .
                                        '</div>' .
                                        '<div style="background-color: #f1f5f9; padding: 15px; text-align: center; font-size: 12px; color: #64748b;">' .
                                            'Ceci est un email automatique, merci de ne pas y répondre.' .
                                        '</div>' .
                                    '</div>'
                                );
                            $mailer->send($emailMessage);
                        } catch (\Exception $e) {}
                    }
                }
                // -------------------------

                $em->flush();
                
                $this->addFlash('success', "La réclamation a été traitée avec succès ! Un email a été envoyé à l'utilisateur.");
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