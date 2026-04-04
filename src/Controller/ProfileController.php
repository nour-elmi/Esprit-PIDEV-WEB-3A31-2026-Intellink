<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bundle\SecurityBundle\Security;

class ProfileController extends AbstractController
{
    // =========================================================================
    //  AFFICHER ET MODIFIER LE PROFIL
    // =========================================================================
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request, 
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MailerInterface $mailer
    ): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $nom = $request->request->get('nom');
            $newEmail = $request->request->get('email');
            $authMethod = $request->request->get('auth_method');
            $avatarUrl = $request->request->get('avatar_url');

            // --- GESTION DES FICHIERS ---
            $imageFile = $request->files->get('image_profil');
            if ($imageFile) {
                $newFilename = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid().'.'.$imageFile->guessExtension();
                $imageFile->move($this->getParameter('profiles_directory'), $newFilename);
                $user->setImage($newFilename);
            } elseif ($avatarUrl) {
                $user->setImage($avatarUrl); // Si on a cliqué sur un avatar par défaut
            }

            $pdfFile = $request->files->get('cv_pdf');
            if ($pdfFile) {
                $newFilename = $slugger->slug(pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid().'.'.$pdfFile->guessExtension();
                $pdfFile->move($this->getParameter('cv_directory'), $newFilename);
                $user->setSkills($newFilename);
            }

            $user->setAuthMethod($authMethod);

            // --- CHANGEMENT D'EMAIL (LOGIQUE DE VÉRIFICATION OTP) ---
            if ($newEmail !== $user->getEmail()) {
                $existingUser = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $newEmail]);
                if ($existingUser) {
                    $this->addFlash('danger', 'Cette adresse e-mail est déjà utilisée.');
                    return $this->redirectToRoute('app_profile');
                }

                // On stocke les modifs en session et on envoie le code
                $codeVerification = sprintf("%06d", mt_rand(1, 999999));
                $session = $request->getSession();
                $session->set('pending_profile_update', [
                    'nom' => $nom,
                    'email' => $newEmail,
                ]);
                $session->set('profile_verification_code', $codeVerification);

                $emailMessage = (new Email())
                    ->from('liontn2004@gmail.com')
                    ->to($newEmail)
                    ->subject('Vérification de votre nouvelle adresse e-mail - Intel_link')
                    ->html("<h2>Votre code de sécurité : {$codeVerification}</h2>");
                $mailer->send($emailMessage);

                return $this->redirectToRoute('app_profile_verify_email');
            }

            // Si l'email n'a pas changé, on sauvegarde directement
            $user->setNom($nom);
            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès !');
            
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('frontUser/profile.html.twig', [
            'user' => $user
        ]);
    }

    // =========================================================================
    //  VÉRIFIER LE CHANGEMENT D'EMAIL
    // =========================================================================
    #[Route('/profile/verify-email', name: 'app_profile_verify_email')]
    public function verifyEmailChange(Request $request, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        $pendingData = $session->get('pending_profile_update');
        $correctCode = $session->get('profile_verification_code');
        $user = $this->getUser();

        if (!$pendingData || !$user) {
            return $this->redirectToRoute('app_profile');
        }

        if ($request->isMethod('POST')) {
            $enteredCode = $request->request->get('code1').$request->request->get('code2').$request->request->get('code3').$request->request->get('code4').$request->request->get('code5').$request->request->get('code6');

            if ($enteredCode === $correctCode) {
                // ✅ LE CODE EST BON : ON MET À JOUR LA BDD
                $user->setNom($pendingData['nom']);
                $user->setEmail($pendingData['email']);
                $entityManager->flush();

                $session->remove('pending_profile_update');
                $session->remove('profile_verification_code');

                $this->addFlash('success', 'Votre adresse e-mail a été modifiée avec succès !');
                return $this->redirectToRoute('app_profile');
            } else {
                $this->addFlash('danger', 'Code incorrect.');
            }
        }

        return $this->render('frontUser/verify_email.html.twig', [
            'email' => $pendingData['email'] // On réutilise votre belle vue OTP !
        ]);
    }

    // =========================================================================
    //  SUPPRIMER LE COMPTE
    // =========================================================================
    #[Route('/profile/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function deleteAccount(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $this->getUser();
        if ($user && $this->isCsrfTokenValid('delete-account', $request->request->get('_token'))) {
            // Déconnecter l'utilisateur
            $request->getSession()->invalidate();
            $security->logout(false);

            // Supprimer de la base
            $entityManager->remove($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_login');
        }
        return $this->redirectToRoute('app_profile');
    }
}