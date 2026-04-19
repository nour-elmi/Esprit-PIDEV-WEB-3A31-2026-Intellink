<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Bundle\SecurityBundle\Security;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        if ($this->getUser()) {
            return $this->redirectToRoute('app_redirect_user');
        }

        if ($request->isMethod('POST')) {
            // V?RIFICATION RECAPTCHA v2
            $client = \Symfony\Component\HttpClient\HttpClient::create();
            $recaptchaResponse = $request->request->get('g-recaptcha-response');
            $responseReq = $client->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
                'body' => [
                    'secret' => $_ENV['RECAPTCHA_SECRET_KEY'] ?? '',
                    'response' => (string) $recaptchaResponse,
                ]
            ]);
            $dataRecaptcha = $responseReq->toArray(false);

            if (empty($dataRecaptcha['success'])) {
                $this->addFlash('error_login', 'Veuillez cocher la case "Je ne suis pas un robot".');
                return $this->redirectToRoute('app_login');
            }
            $email = $request->request->get('_username');
            $password = $request->request->get('_password');
            $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

            if ($user && $passwordHasher->isPasswordValid($user, $password)) {
                
                if ($user->getStatutCompte() === 'EN_ATTENTE') {
                    $this->addFlash('error_login', 'Votre compte pro est en cours de validation par un administrateur.');
                    return $this->redirectToRoute('app_login');
                } elseif ($user->getStatutCompte() === 'REJETE') {
                    $this->addFlash('error_login', 'Votre demande de compte a été refusée.');
                    return $this->redirectToRoute('app_login');
                }
                if ($email === 'admin@intel-link.com' || $email === 'admin@intellink.tn') {
                    $request->getSession()->set('2fa_user_id', $user->getId());
                    return $this->render('frontUser/login.html.twig', [
                        'require_voice_2fa' => true,
                        'last_username' => $email,
                        'error' => null
                    ]);
                }

                $method = $user->getAuthMethod();

                // Modal Email Twig
                if ($method === 'EMAIL') {
                    $code2FA = sprintf("%06d", mt_rand(1, 999999));
                    $request->getSession()->set('2fa_user_id', $user->getId());
                    $request->getSession()->set('2fa_code', $code2FA);

                    $emailMessage = (new Email())
                        ->from('liontn2004@gmail.com')
                        ->to($user->getEmail())
                        ->subject('Votre code de connexion 2FA - Intel_link')
                        ->html("
                            <h2 style='color:#2F80ED;'>Intel_link - Sécurité</h2>
                            <p>Bonjour,</p>
                            <p>Une tentative de connexion a été détectée sur votre compte.</p>
                            <p>Voici votre code de vérification : <b style='font-size: 24px; letter-spacing: 4px;'>{$code2FA}</b></p>
                        ");
                    $mailer->send($emailMessage);

                    return $this->render('frontUser/login.html.twig', [
                        'require_email_2fa' => true,
                        'user_email' => $user->getEmail(),
                        'last_username' => $email,
                        'error' => null
                    ]);
                } 
                // Modal Face ID Twig
                elseif ($method === 'FACE') {
                    $request->getSession()->set('2fa_user_id', $user->getId());
                    return $this->render('frontUser/login.html.twig', [
                        'require_face_2fa' => true,
                        'last_username' => $email,
                        'error' => null
                    ]);
                } elseif ($method === 'VOICE') {
                    $request->getSession()->set('2fa_user_id', $user->getId());
                    return $this->render('frontUser/login.html.twig', [
                        'require_voice_2fa' => true,
                        'last_username' => $email,
                        'error' => null
                    ]);
                }

                $security->login($user, 'security.authenticator.form_login.main');
                return $this->redirectToRoute('app_redirect_user'); 
            } else {
                $this->addFlash('error_login', 'Identifiants incorrects.');
            }
        }

        return $this->render('frontUser/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error'         => $authenticationUtils->getLastAuthenticationError()
        ]);
    }

    // =========================================================================
    //  2. VÉRIFICATION AJAX 2FA (FACE RECOGNITION & EMAIL FALLBACK)
    // =========================================================================

    #[Route('/verify-face/ajax', name: 'app_verify_face_ajax', methods: ['POST'])]
    public function verifyFaceAjax(Request $request, EntityManagerInterface $em, Security $security, HttpClientInterface $httpClient): JsonResponse 
    {
        $session = $request->getSession();
        $userId = $session->get('2fa_user_id');
        if (!$userId) return new JsonResponse(['success' => false, 'message' => 'Session expirée.']);

        $user = $em->getRepository(Utilisateur::class)->find($userId);
        if (!$user || empty($user->getImage())) return new JsonResponse(['success' => false, 'message' => 'Image de profil introuvable.']);

        $data = json_decode($request->getContent(), true);
        $base64Webcam = preg_replace('#^data:image/\w+;base64,#i', '', $data['image'] ?? '');
        $imageName = $user->getImage();
        $base64Profile = '';

        if (filter_var($imageName, FILTER_VALIDATE_URL)) {
            $imgRes = $httpClient->request('GET', $imageName);
            $base64Profile = base64_encode($imgRes->getContent());
        } else {
            $profileImagePath = $this->getParameter('profiles_directory') . '/' . $imageName;
            if (!file_exists($profileImagePath)) return new JsonResponse(['success' => false, 'message' => 'Image locale introuvable.']);
            $base64Profile = base64_encode(file_get_contents($profileImagePath));
        }

        $apiKey = $_ENV['FACEPLUSPLUS_API_KEY'] ?? '';
        $apiSecret = $_ENV['FACEPLUSPLUS_API_SECRET'] ?? '';

        try {
            $response = $httpClient->request('POST', 'https://api-us.faceplusplus.com/facepp/v3/compare', [
                'body' => [ 'api_key' => $apiKey, 'api_secret' => $apiSecret, 'image_base64_1' => $base64Profile, 'image_base64_2' => $base64Webcam ]
            ]);
            $result = $response->toArray(false);

            if (isset($result['confidence'])) {
                if ((float)$result['confidence'] >= 70.0) {
                    $session->remove('2fa_user_id');
                    $security->login($user, 'security.authenticator.form_login.main');
                    return new JsonResponse(['success' => true]);
                }
                return new JsonResponse(['success' => false, 'message' => "Visage non reconnu (Score: {$result['confidence']}%)."]);
            }
            return new JsonResponse(['success' => false, 'message' => 'Erreur API Face++.']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur technique.']);
        }
    }

    #[Route('/fallback-2fa-email/ajax', name: 'app_fallback_2fa_email_ajax', methods: ['POST'])]
    public function fallback2faEmailAjax(Request $request, EntityManagerInterface $em, MailerInterface $mailer): JsonResponse
    {
        $session = $request->getSession();
        $userId = $session->get('2fa_user_id');
        if(!$userId) return new JsonResponse(['success'=>false], 400);

        $user = $em->getRepository(Utilisateur::class)->find($userId);
        $code2FA = sprintf("%06d", mt_rand(1, 999999));
        $session->set('2fa_code', $code2FA);

        $emailMessage = (new Email())
            ->from('liontn2004@gmail.com')
            ->to($user->getEmail())
            ->subject('Code de sécurité de secours - IntelLink')
            ->html("<h2 style='color:#e74c3c;'>Alerte de sécurité</h2><p>La reconnaissance faciale a échoué. Voici votre code de secours : <b style='font-size: 24px; letter-spacing: 4px;'>{$code2FA}</b></p>");
        
        try {
            $mailer->send($emailMessage);
            return new JsonResponse(['success'=>true, 'email'=>$user->getEmail()]);
        } catch (\Exception $e) {
            return new JsonResponse(['success'=>false]);
        }
    }

    #[Route('/verify-email-2fa/ajax', name: 'app_verify_email_2fa_ajax', methods: ['POST'])]
    public function verifyEmail2faAjax(Request $request, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $session = $request->getSession();
        $userId = $session->get('2fa_user_id');
        $correctCode = $session->get('2fa_code');

        if(!$userId) return new JsonResponse(['success'=>false, 'message'=>'Session expirée'], 400);

        $data = json_decode($request->getContent(), true);
        if (str_replace(' ', '', $data['code'] ?? '') === $correctCode) {
            $user = $em->getRepository(Utilisateur::class)->find($userId);
            $security->login($user, 'security.authenticator.form_login.main');
            $session->remove('2fa_user_id');
            $session->remove('2fa_code');
            return new JsonResponse(['success'=>true]);
        }

        return new JsonResponse(['success'=>false, 'message'=>'Code incorrect']);
    }

    // =========================================================================
    //  3. INSCRIPTION (SIGNUP) AVEC FICHIERS ET EMAIL
    // =========================================================================
    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher, 
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MailerInterface $mailer,
        ValidatorInterface $validator
    ): Response {
        $session = $request->getSession();
        $email = $request->request->get('email');
        $nom = $request->request->get('nom');
        
        // --- BAD WORD CHECK EARLY INTERVENTION AVEC PURGOMALUM ---
        $tempUser = new Utilisateur();
        $tempUser->setNom($nom);
        $errors = $validator->validateProperty($tempUser, 'nom');
        if (count($errors) > 0) {
            $this->addFlash('error_signup', $errors[0]->getMessage());
            return $this->redirectToRoute('app_login');
        }

        try {
            $client = \Symfony\Component\HttpClient\HttpClient::create();
            // PurgoMalum returns plain text 'true' or 'false'
            $response = $client->request('GET', 'https://www.purgomalum.com/service/containsprofanity?text=' . urlencode((string) $nom));
            $plainTextResponse = $response->getContent();
            
            // Check specifically for precise text "true" with no whitespace
            if (trim($plainTextResponse) === 'true') {
                $this->addFlash('error_signup', 'Le nom est identifié comme inapproprié.');
                return $this->redirectToRoute('app_login');
            }
        } catch (\Exception $e) {
            // Nothing - if API is down, just proceed to let user sign up
        }
        // -----------------------------------------

        $existingUser = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $this->addFlash('error_signup', 'Cette adresse email est déjà utilisée.');
            return $this->redirectToRoute('app_login');
        }

        $avatarUrl = $request->request->get('avatar_url');
        $imageFile = $request->files->get('image_profil');
        $imageToSave = null;

        if (!empty($avatarUrl)) {
            $imageToSave = $avatarUrl;
        } elseif ($imageFile) {
            $newFilename = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid().'.'.$imageFile->guessExtension();
            try {
                $imageFile->move($this->getParameter('profiles_directory'), $newFilename);
                $imageToSave = $newFilename;
            } catch (FileException $e) {}
        }

        $cvFilename = null;
        $pdfFile = $request->files->get('cv_pdf');
        if ($pdfFile) {
            $newFilename = $slugger->slug(pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME)).'-'.uniqid().'.'.$pdfFile->guessExtension();
            try {
                $pdfFile->move($this->getParameter('cv_directory'), $newFilename);
                $cvFilename = $newFilename;
            } catch (FileException $e) {}
        }

        $roleDemande = $request->request->get('role_demande');
        $finalRole = (!$roleDemande || $roleDemande === 'utilisateur simple') ? 'ROLE_USER' : 'ROLE_' . str_replace(' ', '_', strtoupper($roleDemande));
        $statut = ($finalRole === 'ROLE_USER') ? 'ACTIF' : 'EN_ATTENTE';

        $pendingUser = [
            'nom' => $request->request->get('nom'),
            'email' => $email,
            'password' => $passwordHasher->hashPassword(new Utilisateur(), $request->request->get('password')),
            'role' => $finalRole,
            'statut' => $statut,
            'image' => $imageToSave,
            'cv' => $cvFilename
        ];

        $codeVerification = sprintf("%06d", mt_rand(1, 999999));
        
        $session->set('pending_user_data', $pendingUser);
        $session->set('verification_code', $codeVerification);
        $session->set('verification_email', $email);

        $emailMessage = (new Email())
            ->from('liontn2004@gmail.com')
            ->to($email)
            ->subject('Code de vérification - Intel_link')
            ->html("<h2>Votre code : {$codeVerification}</h2>");
        $mailer->send($emailMessage);

        return $this->redirectToRoute('app_verify_email');
    }

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
                $user = new Utilisateur();
                $user->setNom($userData['nom']);
                $user->setEmail($userData['email']);
                $user->setMdp($userData['password']);
                $user->setRole($userData['role']);
                $user->setStatutCompte($userData['statut']);
                $user->setImage($userData['image']); 
                $user->setSkills($userData['cv']);
                $user->setAuthMethod('EMAIL');
                $user->setIsGoogleAuthenticatorEnabled(false);
                $user->setPasswordRecoveryMethod('EMAIL');

                $entityManager->persist($user);
                $entityManager->flush();

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
    //  4. REDIRECTION ET LOGOUT
    // =========================================================================
    #[Route(path: '/redirect-user', name: 'app_redirect_user')]
    public function redirectUser(): Response
    {
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        $roles = $user->getRoles();
        
        if (in_array('ROLE_ADMIN', $roles)) return $this->redirectToRoute('app_admin_users'); 
        if (in_array('ROLE_FORMATEUR', $roles)) return $this->redirectToRoute('app_formation_index'); 
        if (in_array('ROLE_RECRUTEUR', $roles)) return $this->redirectToRoute('app_recruteur_dashboard'); 
        if (in_array('ROLE_CHEF_PROJET', $roles)) return $this->redirectToRoute('app_back_projets'); 
        if (in_array('ROLE_USER', $roles)) return $this->redirectToRoute('app_home'); 

        return $this->redirectToRoute('app_home');
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void {
        throw new \LogicException('Cette méthode peut être vide.');
    }

    // =========================================================================
    //  5. MOT DE PASSE OUBLIÉ (TOUT EN AJAX POUR UNE NAVIGATION FLUIDE)
    // =========================================================================
    #[Route('/forgot-password/request-ajax', name: 'app_forgot_password_request_ajax', methods: ['POST'])]
    public function forgotPasswordRequestAjax(Request $request, EntityManagerInterface $em, MailerInterface $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = trim($data['email'] ?? '');
        $user = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return new JsonResponse(['success' => true, 'type' => 'EMAIL', 'email' => $email]);
        }

        $session = $request->getSession();
        $session->set('reset_email', $user->getEmail());

        if ($user->getPasswordRecoveryMethod() === 'GOOGLE_AUTHENTICATOR' && $user->isGoogleAuthenticatorEnabled()) {
            $session->set('reset_type', '2FA');
            return new JsonResponse(['success' => true, 'type' => '2FA', 'email' => $email]);
        } else {
            $session->set('reset_type', 'EMAIL');
            $code = sprintf("%06d", mt_rand(1, 999999));
            $session->set('reset_code', $code);

            $emailMessage = (new Email())
                ->from('liontn2004@gmail.com')
                ->to($user->getEmail())
                ->subject('Récupération de mot de passe - IntelLink')
                ->html("<h2 style='color:#2F80ED;'>Code de récupération : {$code}</h2><p>Ce code est valable pour réinitialiser votre mot de passe.</p>");
            $mailer->send($emailMessage);

            return new JsonResponse(['success' => true, 'type' => 'EMAIL', 'email' => $email]);
        }
    }

    #[Route('/forgot-password/verify-ajax', name: 'app_forgot_password_verify_ajax', methods: ['POST'])]
    public function forgotPasswordVerifyAjax(Request $request, EntityManagerInterface $em, GoogleAuthenticatorInterface $authenticator): JsonResponse
    {
        $session = $request->getSession();
        $email = $session->get('reset_email');
        $type = $session->get('reset_type');

        if (!$email) return new JsonResponse(['success' => false, 'message' => 'Session expirée.'], 400);

        $data = json_decode($request->getContent(), true);
        $enteredCode = str_replace(' ', '', $data['code'] ?? '');
        $isValid = false;

        if ($type === 'EMAIL') {
            $isValid = ($enteredCode === $session->get('reset_code'));
        } else {
            $user = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
            $isValid = $authenticator->checkCode($user, $enteredCode);
        }

        if ($isValid) {
            $session->set('reset_verified', true);
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Code incorrect.']);
    }

    #[Route('/forgot-password/new-ajax', name: 'app_forgot_password_new_ajax', methods: ['POST'])]
    public function forgotPasswordNewAjax(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $session = $request->getSession();
        if (!$session->get('reset_verified')) return new JsonResponse(['success' => false, 'message' => 'Non autorisé.'], 403);

        $data = json_decode($request->getContent(), true);
        $newPassword = $data['password'] ?? '';

        if (strlen($newPassword) < 8) {
            return new JsonResponse(['success' => false, 'message' => 'Mot de passe trop court.']);
        }

        $user = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $session->get('reset_email')]);
        $user->setMdp($hasher->hashPassword($user, $newPassword));
        $em->flush();

        $session->remove('reset_email');
        $session->remove('reset_type');
        $session->remove('reset_code');
        $session->remove('reset_verified');

        $this->addFlash('success', 'Mot de passe réinitialisé ! Vous pouvez vous connecter.');
        return new JsonResponse(['success' => true]);
    }

    // =========================================================================
    //  6. GITHUB OAUTH2
    // =========================================================================
    #[Route('/connect/github', name: 'app_github_connect')]
    public function githubConnect(UrlGeneratorInterface $urlGenerator): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_redirect_user');
        }

        $clientId = $_ENV['GITHUB_CLIENT_ID'] ?? '';
        $redirectUri = $urlGenerator->generate('app_github_check', [], UrlGeneratorInterface::ABSOLUTE_URL);
        
        $url = "https://github.com/login/oauth/authorize?client_id={$clientId}&redirect_uri={$redirectUri}&scope=user:email&prompt=consent";
        
        return $this->redirect($url);
    }

    #[Route('/connect/github/check', name: 'app_github_check')]
    public function githubCheck(
        Request $request, 
        HttpClientInterface $httpClient, 
        EntityManagerInterface $em, 
        Security $security,
        UserPasswordHasherInterface $passwordHasher,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        $code = $request->query->get('code');
        if (!$code) {
            $this->addFlash('error_login', 'Connexion GitHub annulée.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $response = $httpClient->request('POST', 'https://github.com/login/oauth/access_token', [
                'headers' => ['Accept' => 'application/json'],
                'body' => [
                    'client_id' => $_ENV['GITHUB_CLIENT_ID'] ?? '',
                    'client_secret' => $_ENV['GITHUB_CLIENT_SECRET'] ?? '',
                    'code' => $code,
                    'redirect_uri' => $urlGenerator->generate('app_github_check', [], UrlGeneratorInterface::ABSOLUTE_URL)
                ]
            ]);

            $data = $response->toArray();
            $accessToken = $data['access_token'] ?? null;

            if (!$accessToken) {
                throw new \Exception('Impossible de récupérer le token GitHub.');
            }

            $userResponse = $httpClient->request('GET', 'https://api.github.com/user', [
                'headers' => [ 'Authorization' => 'Bearer ' . $accessToken, 'Accept' => 'application/json' ]
            ]);
            $githubUser = $userResponse->toArray();

            $emailsResponse = $httpClient->request('GET', 'https://api.github.com/user/emails', [
                'headers' => [ 'Authorization' => 'Bearer ' . $accessToken, 'Accept' => 'application/json' ]
            ]);
            $emails = $emailsResponse->toArray();
            
            $primaryEmail = null;
            foreach ($emails as $emailData) {
                if ($emailData['primary'] && $emailData['verified']) {
                    $primaryEmail = $emailData['email'];
                    break;
                }
            }

            if (!$primaryEmail) {
                throw new \Exception('Veuillez vérifier votre adresse email sur GitHub pour continuer.');
            }

            $user = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $primaryEmail]);

            if (!$user) {
                $user = new Utilisateur();
                $user->setEmail($primaryEmail);
                $user->setNom($githubUser['name'] ?? $githubUser['login']);
                $user->setRole('ROLE_USER');
                $user->setStatutCompte('ACTIF');
                $user->setAuthMethod('GITHUB'); 
                $user->setIsGoogleAuthenticatorEnabled(false);
                $user->setPasswordRecoveryMethod('EMAIL');
                
                $randomPassword = bin2hex(random_bytes(16));
                $user->setMdp($passwordHasher->hashPassword($user, $randomPassword));
                
                if (isset($githubUser['avatar_url'])) {
                    $user->setImage($githubUser['avatar_url']);
                }

                $em->persist($user);
                $em->flush();
            } else {
                $hasChanges = false;
                if (empty($user->getImage()) && isset($githubUser['avatar_url'])) {
                    $user->setImage($githubUser['avatar_url']);
                    $hasChanges = true;
                }
                if ($user->getAuthMethod() !== 'GITHUB') {
                    $user->setAuthMethod('GITHUB');
                    $hasChanges = true;
                }
                if ($hasChanges) {
                    $em->flush();
                }
            }

            if ($user->getStatutCompte() === 'EN_ATTENTE') {
                $this->addFlash('error_login', 'Votre compte pro est en cours de validation par un administrateur.');
                return $this->redirectToRoute('app_login');
            } elseif ($user->getStatutCompte() === 'REJETE') {
                $this->addFlash('error_login', 'Votre demande de compte a été refusée.');
                return $this->redirectToRoute('app_login');
            }

            $security->login($user, 'security.authenticator.form_login.main');
            return $this->redirectToRoute('app_redirect_user');

        } catch (\Exception $e) {
            $this->addFlash('error_login', 'Erreur GitHub : ' . $e->getMessage());
            return $this->redirectToRoute('app_login');
        }
    }

    #[Route('/verify-voice/ajax', name: 'app_verify_voice_ajax', methods: ['POST'])]
    public function verifyVoiceAjax(Request $request, EntityManagerInterface $em, Security $security, HttpClientInterface $httpClient): JsonResponse 
    {
        $session = $request->getSession();
        $userId = $session->get('2fa_user_id');
        if (!$userId) return new JsonResponse(['success' => false, 'message' => 'Session expirée.']);

        $user = $em->getRepository(Utilisateur::class)->find($userId);
        if (!$user) return new JsonResponse(['success' => false, 'message' => 'Utilisateur introuvable.']);

        $audioFile = $request->files->get('audio');
        if (!$audioFile) return new JsonResponse(['success' => false, 'message' => 'Aucun fichier audio reçu.']);

        $witAiToken = $_ENV['WIT_AI_TOKEN'] ?? 'ONZNBQMFNANLV7M6TVKHQULM37NFQC4O';

        // 1. On reçoit désormais un vrai WAV compressé PCM du JavaScript
        $mimeType = $request->request->get('mimeType', 'audio/wav');
        
        try {
            $response = $httpClient->request('POST', 'https://api.wit.ai/dictation?v=20240304', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $witAiToken,
                    'Content-Type' => $mimeType,
                ],
                'body' => file_get_contents($audioFile->getPathname())
            ]);

            $jsonResponse = $response->getContent(false); // false empêche de crasher si l'IA ne comprend rien

            // 2. Extraction du texte final (bypass du formatage NDJSON/pretty-print)
            preg_match_all('/"text":\s*"([^"]+)"/', $jsonResponse, $matches);
            $texteEntendu = '';
            
            if (!empty($matches[1])) {
                $rawText = end($matches[1]); // Prendre la dernière valeur 'text' trouvée
                // json_decode sur une chaîne entourée de guillemets décode nativement les \u00e8 en é/è/à
                $decoded = json_decode('"' . $rawText . '"');
                $texteEntendu = $decoded ? trim($decoded) : trim($rawText);
            }

            // Vérification du mot de passe vocal
            if ($texteEntendu !== '' && (stripos($texteEntendu, 'accès') !== false || stripos($texteEntendu, 'sécurisé') !== false || stripos($texteEntendu, 'acces') !== false)) {
                $session->remove('2fa_user_id');
                $security->login($user, 'security.authenticator.form_login.main');
                return new JsonResponse(['success' => true]);
            }

            // Retourne la vraie réponse de l'API Wit.ai pour le debug
            $debugResponse = substr(str_replace('"', "'", $jsonResponse), 0, 300); // 300 premiers caractères pour voir l'erreur exacte
            return new JsonResponse(['success' => false, 'message' => "L'IA a entendu : '{$texteEntendu}'. / Debug API Wit : {$debugResponse}"]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur technique lors de l\'analyse vocale : ' . $e->getMessage()]);
        }
    }
}