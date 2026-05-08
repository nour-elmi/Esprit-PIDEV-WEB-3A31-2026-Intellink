<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;

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
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $nom = (string) $request->request->get('nom', '');
            $newEmail = (string) $request->request->get('email', '');
            $authMethod = (string) $request->request->get('auth_method', '');
            $avatarUrl = (string) $request->request->get('avatar_url', '');

            // --- GESTION DES FICHIERS (IMAGE & PDF) ---
            $imageFile = $request->files->get('image_profil');
            if ($imageFile) {
                $newFilename = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid().'.'.$imageFile->guessExtension();
                try {
                    $profilesDirectory = $this->getParameter('profiles_directory');
                    if (!is_string($profilesDirectory) || $profilesDirectory === '') {
                        throw new FileException('profiles_directory invalide');
                    }
                    $imageFile->move($profilesDirectory, $newFilename);
                    $user->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload de l\'image.');
                }
            } elseif ($avatarUrl) {
                $user->setImage($avatarUrl);
            }

            $pdfFile = $request->files->get('cv_pdf');
            if ($pdfFile) {
                $newFilename = $slugger->slug(pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid().'.'.$pdfFile->guessExtension();
                try {
                    $cvDirectory = $this->getParameter('cv_directory');
                    if (!is_string($cvDirectory) || $cvDirectory === '') {
                        throw new FileException('cv_directory invalide');
                    }
                    $pdfFile->move($cvDirectory, $newFilename);
                    $user->setSkills($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'upload du CV.');
                }
            }

            // --- GESTION DU RECOUVREMENT DE MOT DE PASSE ---
            $recoveryMethod = (string) $request->request->get('recovery_method', '');
            
            if ($recoveryMethod === 'GOOGLE_AUTHENTICATOR') {
                if (!$user->isGoogleAuthenticatorEnabled()) {
                    $user->setPasswordRecoveryMethod('EMAIL');
                    $this->addFlash('warning', 'Configuration requise : Vous devez lier votre appareil et saisir le code à 6 chiffres avant d\'activer cette méthode.');
                } else {
                    $user->setPasswordRecoveryMethod('GOOGLE_AUTHENTICATOR');
                }
            } else {
                $user->setPasswordRecoveryMethod('EMAIL');
            }

            if ($authMethod !== '') {
                $user->setAuthMethod($authMethod);
            }

            // --- CHANGEMENT D'EMAIL (LOGIQUE DE VÉRIFICATION OTP) ---
            if ($newEmail !== $user->getEmail()) {
                $existingUser = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $newEmail]);
                if ($existingUser) {
                    $this->addFlash('danger', 'Cette adresse e-mail est déjà utilisée.');
                    return $this->redirectToRoute('app_profile');
                }

                $codeVerification = sprintf("%06d", mt_rand(1, 999999));
                $session = $request->getSession();
                $session->set('pending_profile_update', [
                    'nom' => $nom,
                    'email' => $newEmail,
                ]);
                $session->set('profile_verification_code', $codeVerification);

                $emailMessage = (new Email())
                    ->from('liontn2004@gmail.com')
                    ->to((string) $newEmail)
                    ->subject('Vérification de votre nouvelle adresse e-mail - IntelLink')
                    ->html("<h2>Votre code de sécurité : {$codeVerification}</h2>");
                
                try {
                    $mailer->send($emailMessage);
                    return $this->redirectToRoute('app_profile_verify_email');
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'envoi de l\'e-mail de vérification.');
                }
            }

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
        // CHANGEMENT: typer explicitement l'utilisateur connecte pour PHPStan.
        // Ancien code (garde): $user = $this->getUser();
        $user = $this->getUser();

        // Ancien code (garde): if (!$pendingData || !$user) {
        if (!$pendingData || !($user instanceof Utilisateur)) {
            return $this->redirectToRoute('app_profile');
        }

        if ($request->isMethod('POST')) {
            $enteredCode = $request->request->get('code1').$request->request->get('code2').$request->request->get('code3').$request->request->get('code4').$request->request->get('code5').$request->request->get('code6');

            if ($enteredCode === $correctCode) {
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
            'email' => $pendingData['email']
        ]);
    }

    // =========================================================================
    //  SUPPRIMER LE COMPTE
    // =========================================================================
    #[Route('/profile/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function deleteAccount(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $this->getUser();
        if ($user instanceof Utilisateur && $this->isCsrfTokenValid('delete-account', (string) $request->request->get('_token'))) {
            $request->getSession()->invalidate();
            $security->logout(false);

            $entityManager->remove($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_login');
        }
        return $this->redirectToRoute('app_profile');
    }

    // =========================================================================
    //  MODIFIER LE MOT DE PASSE
    // =========================================================================
    #[Route('/profile/password', name: 'app_profile_password', methods: ['POST'])]
    public function updatePassword(
        Request $request, 
        EntityManagerInterface $em, 
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('change_password', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Erreur de sécurité : Token invalide.');
            return $this->redirectToRoute('app_profile');
        }

        $oldPassword = (string) $request->request->get('old_password', '');
        $newPassword = (string) $request->request->get('new_password', '');
        $confirmPassword = (string) $request->request->get('confirm_password', '');

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('danger', 'Les nouveaux mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_profile');
        }

        if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
            $this->addFlash('danger', 'Identification échouée : L\'ancien mot de passe est incorrect.');
            return $this->redirectToRoute('app_profile');
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $em->flush();

        $this->addFlash('success', '🔐 Le coffre-fort a été refermé. Votre mot de passe a été mis à jour !');
        
        return $this->redirectToRoute('app_profile');
    }

    // =========================================================================
    //  GÉNÉRER LE QR CODE EN AJAX
    // =========================================================================
    #[Route('/profile/2fa/generate-ajax', name: 'app_profile_2fa_generate_ajax', methods: ['POST'])]
    public function generate2FAAjax(
        GoogleAuthenticatorInterface $authenticator, 
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return new JsonResponse(['error' => 'Non autorise'], 403);
        }

        if (!$user->getGoogleAuthenticatorSecret()) {
            $secret = $authenticator->generateSecret();
            $user->setGoogleAuthenticatorSecret($secret);
            $em->flush();
        }

        $qrCodeContent = $authenticator->getQRContent($user);
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($qrCodeContent);

        return new JsonResponse([
            'qrCodeUrl' => $qrCodeUrl,
            'secret' => $user->getGoogleAuthenticatorSecret()
        ]);
    }

   // =========================================================================
    //  VÉRIFIER LE CODE À 6 CHIFFRES EN AJAX (ACTIVER LE 2FA)
    // =========================================================================
    #[Route('/profile/2fa/verify-ajax', name: 'app_profile_2fa_verify_ajax', methods: ['POST'])]
    public function verify2FAAjax(
        Request $request,
        GoogleAuthenticatorInterface $authenticator, 
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return new JsonResponse(['success' => false, 'message' => 'Non autorise.'], 403);
        }

        // 1. Lire les données de façon 100% sécurisée
        $content = $request->getContent();
        $data = json_decode($content, true);

        // 2. Éviter le plantage si $data est null
        $code = (is_array($data) && isset($data['code'])) ? (string) $data['code'] : '';

        // 3. Supprimer les espaces éventuels (ex: "866 704" devient "866704")
        $code = str_replace(' ', '', $code);

        // 4. Vérification
        if ($authenticator->checkCode($user, $code)) {
            $user->setIsGoogleAuthenticatorEnabled(true);
            $em->flush();
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Le code est incorrect ou a expiré.']);
    }
}


