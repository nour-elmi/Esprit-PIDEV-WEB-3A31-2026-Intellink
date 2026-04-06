<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\formation\FormationController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Bundle\SecurityBundle\Security;

class AuthController extends AbstractController
{
    // =========================================================================
    //  1. CONNEXION (LOGIN) AVEC INTERCEPTION 2FA ET STATUT
    // =========================================================================
    #[Route(path: '/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request, 
        EntityManagerInterface $entityManager, 
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer,
        Security $security,
        AuthenticationUtils $authenticationUtils
    ): Response {
        // Si déjà connecté, on le renvoie à l'accueil
        if ($this->getUser()) {
            return $this->redirectToRoute('app_redirect_user');
        }

        if ($request->isMethod('POST')) {
            $email = $request->request->get('_username');
            $password = $request->request->get('_password');

            $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

            // Vérification du mot de passe
            if ($user && $passwordHasher->isPasswordValid($user, $password)) {
                
                // VÉRIFICATION DU STATUT
                if ($user->getStatutCompte() === 'EN_ATTENTE') {
                    $this->addFlash('error_login', 'Votre compte pro est en cours de validation par un administrateur.');
                    return $this->redirectToRoute('app_login');
                } elseif ($user->getStatutCompte() === 'REJETE') {
                    $this->addFlash('error_login', 'Votre demande de compte a été refusée.');
                    return $this->redirectToRoute('app_login');
                }

                // LOGIQUE DE DOUBLE AUTHENTIFICATION (2FA)
                $method = $user->getAuthMethod();

                if ($method === 'EMAIL') {
                    $code2FA = sprintf("%06d", mt_rand(1, 999999));
                    $request->getSession()->set('2fa_user_id', $user->getId());
                    $request->getSession()->set('2fa_code', $code2FA);

                    $emailMessage = (new Email())
                        ->from('liontn2004@gmail.com')
                        ->to($user->getEmail())
                        ->subject('Votre code de connexion 2FA - Intel_link')
                        ->html("
                            <h2 style='color:#2ea043;'>Intel_link - Sécurité</h2>
                            <p>Bonjour,</p>
                            <p>Une tentative de connexion a été détectée sur votre compte.</p>
                            <p>Voici votre code de vérification : <b style='font-size: 24px; letter-spacing: 4px;'>{$code2FA}</b></p>
                        ");
                    $mailer->send($emailMessage);

                    return $this->redirectToRoute('app_verify_2fa');

                } elseif ($method === 'FACE' || $method === 'VOICE') {
                    $this->addFlash('error_login', 'La 2FA Face/Voice est en cours de développement sur le web.');
                    return $this->redirectToRoute('app_login');
                }

                // CONNEXION DIRECTE
                $security->login($user, 'security.authenticator.form_login.main');
                return $this->redirectToRoute('app_redirect_user'); 
            } else {
                // Si le mot de passe est faux
                $this->addFlash('error_login', 'Identifiants incorrects.');
            }
        }

        // On renvoie obligatoirement les variables attendues par Twig
        return $this->render('frontUser/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error'         => $authenticationUtils->getLastAuthenticationError()
        ]);
    }

    // =========================================================================
    //  2. INSCRIPTION (SIGNUP) AVEC FICHIERS ET EMAIL
    // =========================================================================
    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher, 
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MailerInterface $mailer
    ): Response {
        $session = $request->getSession();
        $email = $request->request->get('email');

        // On vérifie quand même si l'email existe déjà en DB
        $existingUser = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $this->addFlash('error_signup', 'Cette adresse email est déjà utilisée.');
            return $this->redirectToRoute('app_login');
        }

        // --- GESTION DES FICHIERS ET AVATARS (On les stocke physiquement tout de suite) ---
        $avatarUrl = $request->request->get('avatar_url');
        $imageFile = $request->files->get('image_profil');
        $imageToSave = null;

        if (!empty($avatarUrl)) {
            // L'utilisateur a choisi un avatar depuis la liste modale
            $imageToSave = $avatarUrl;
        } elseif ($imageFile) {
            // L'utilisateur a uploadé une vraie photo
            $newFilename = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid().'.'.$imageFile->guessExtension();
            try {
                $imageFile->move($this->getParameter('profiles_directory'), $newFilename);
                $imageToSave = $newFilename;
            } catch (FileException $e) {
                // Gestion d'erreur silencieuse ou log
            }
        }

        $cvFilename = null;
        $pdfFile = $request->files->get('cv_pdf');
        if ($pdfFile) {
            $newFilename = $slugger->slug(pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid().'.'.$pdfFile->guessExtension();
            try {
                $pdfFile->move($this->getParameter('cv_directory'), $newFilename);
                $cvFilename = $newFilename;
            } catch (FileException $e) {
                // Gestion d'erreur silencieuse ou log
            }
        }

        // --- CALCUL DU RÔLE ---
        $roleDemande = $request->request->get('role_demande');
        $finalRole = (!$roleDemande || $roleDemande === 'utilisateur simple') ? 'ROLE_USER' : 'ROLE_' . str_replace(' ', '_', strtoupper($roleDemande));
        $statut = ($finalRole === 'ROLE_USER') ? 'ACTIF' : 'EN_ATTENTE';

        // --- STOCKAGE EN SESSION (Zone de transit) ---
        // On ne crée pas l'objet Entity ici pour éviter les erreurs de persistance
        $pendingUser = [
            'nom' => $request->request->get('nom'),
            'email' => $email,
            'password' => $passwordHasher->hashPassword(new Utilisateur(), $request->request->get('password')),
            'role' => $finalRole,
            'statut' => $statut,
            'image' => $imageToSave, // L'image (uploadée, url, ou null) est bien sauvegardée ici
            'cv' => $cvFilename
        ];

        $codeVerification = sprintf("%06d", mt_rand(1, 999999));
        
        $session->set('pending_user_data', $pendingUser);
        $session->set('verification_code', $codeVerification);
        $session->set('verification_email', $email);

        // --- ENVOI DU MAIL ---
        $emailMessage = (new Email())
            ->from('liontn2004@gmail.com')
            ->to($email)
            ->subject('Code de vérification - Intel_link')
            ->html("<h2>Votre code : {$codeVerification}</h2>");

        $mailer->send($emailMessage);

        return $this->redirectToRoute('app_verify_email');
    }

    // =========================================================================
    //  3. DÉCONNEXION (LOGOUT)
    // =========================================================================
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Cette méthode peut rester vide, Symfony intercepte la route pour détruire la session.
        throw new \LogicException('Cette méthode peut être vide.');
    }

    // =========================================================================
    //  VÉRIFICATION DE L'EMAIL (Code à 6 chiffres)
    // =========================================================================
    #[Route(path: '/verify-email', name: 'app_verify_email')]
    public function verifyEmail(Request $request, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        $userData = $session->get('pending_user_data');
        $correctCode = $session->get('verification_code');

        if (!$userData) {
            return $this->redirectToRoute('app_register');
        }

        if ($request->isMethod('POST')) {
            $enteredCode = $request->request->get('code1').$request->request->get('code2').$request->request->get('code3').$request->request->get('code4').$request->request->get('code5').$request->request->get('code6');
            
            if ($enteredCode === $correctCode) {
                // ✅ LE CODE EST BON : ON ENREGISTRE EN BASE DE DONNÉES
                $user = new Utilisateur();
                $user->setNom($userData['nom']);
                $user->setEmail($userData['email']);
                $user->setMdp($userData['password']);
                $user->setRole($userData['role']);
                $user->setStatutCompte($userData['statut']);
                $user->setImage($userData['image']); // Récupère l'image qui a été validée dans l'étape d'avant
                $user->setSkills($userData['cv']);
                $user->setAuthMethod('EMAIL'); // Sécurité 2FA activée par défaut

                $entityManager->persist($user);
                $entityManager->flush();

                // On nettoie la session
                $session->remove('pending_user_data');
                $session->remove('verification_code');
                $session->remove('verification_email');

                $this->addFlash('success', 'Inscription réussie ! Vous pouvez vous connecter.');
                return $this->redirectToRoute('app_login');
            } else {
                $this->addFlash('error', 'Code incorrect.');
            }
        }

        return $this->render('frontUser/verify_email.html.twig', [
            'email' => $session->get('verification_email')
        ]);
    }

    // =========================================================================
    //  AIGUILLAGE DES RÔLES PRO (Équivalent de finalizeLogin en Java)
    // =========================================================================
    #[Route(path: '/redirect-user', name: 'app_redirect_user')]
    public function redirectUser(): Response
    {
        // 1. On récupère l'utilisateur depuis la Session Symfony
        $user = $this->getUser();

        // Sécurité : si personne n'est connecté, retour au login
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // 2. On récupère le tableau des rôles de la session
        $roles = $user->getRoles();
        
        // 3. On redirige vers la bonne interface (Vos fichiers FXML traduits en Web)
        if (in_array('ROLE_ADMIN', $roles)) {
            // Équivalent de /adminUserList.fxml
            return $this->redirectToRoute('app_admin_users'); 
            
        } elseif (in_array('ROLE_FORMATEUR', $roles)) {
            // Équivalent de /FrontFormateur.fxml (Le fichier de Sarra)
            return $this->redirectToRoute('app_formation_index'); 
            
        } elseif (in_array('ROLE_RECRUTEUR', $roles)) {
            // Équivalent de /FrontRecruteur.fxml (Le fichier de Nour)
            return $this->redirectToRoute('app_recruteur_dashboard'); 
            
        } elseif (in_array('ROLE_CHEF_PROJET', $roles)) {
            // Équivalent de /FrontChefProjet.fxml (Votre fichier)
            return $this->redirectToRoute('app_home'); 
        }

        // 4. Par défaut : Utilisateur simple (Équivalent de /index.fxml)
        return $this->redirectToRoute('app_home');
    }

    // =========================================================================
    //  VÉRIFICATION DE LA 2FA (LOGIN)
    // =========================================================================
    #[Route(path: '/verify-2fa', name: 'app_verify_2fa')]
    public function verify2FA(
        Request $request, 
        EntityManagerInterface $entityManager, 
        Security $security
    ): Response {
        $session = $request->getSession();
        $userId = $session->get('2fa_user_id');
        $correctCode = $session->get('2fa_code');

        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        // On récupère l'utilisateur en attente
        $user = $entityManager->getRepository(Utilisateur::class)->find($userId);

        if ($request->isMethod('POST')) {
            $enteredCode = $request->request->get('code1') . 
                           $request->request->get('code2') . 
                           $request->request->get('code3') . 
                           $request->request->get('code4') . 
                           $request->request->get('code5') . 
                           $request->request->get('code6');

            if ($enteredCode === $correctCode) {
                // ✅ LE CODE EST BON : ON LE CONNECTE ! (finalizeLogin)
                $session->remove('2fa_user_id');
                $session->remove('2fa_code');

                $security->login($user, 'security.authenticator.form_login.main');
                
                // Redirection vers le tableau de bord (Aiguillage)
                return $this->redirectToRoute('app_redirect_user'); 
            } else {
                $this->addFlash('error', 'Code incorrect, veuillez réessayer.');
            }
        }

        return $this->render('frontUser/verify_email.html.twig', [
            'email' => $user->getEmail() // On réutilise le même design Twig des 6 cases !
        ]);
    }
}