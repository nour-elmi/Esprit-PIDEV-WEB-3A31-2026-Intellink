<?php

namespace App\Service;

use App\Repository\UtilisateurRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LoginService
{
    private const LOGIN_TEMPLATE = 'frontUser/login.html.twig';
    private const ADMIN_EMAILS = [
        'admin@intel-link.com',
        'admin@intellink.tn',
    ];

    private UtilisateurRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private MailerInterface $mailer;
    private Security $security;
    private RecaptchaVerifier $recaptchaVerifier;
    private CodeGeneratorInterface $codeGenerator;

    public function __construct(
        UtilisateurRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer,
        Security $security,
        RecaptchaVerifier $recaptchaVerifier,
        CodeGeneratorInterface $codeGenerator
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->mailer = $mailer;
        $this->security = $security;
        $this->recaptchaVerifier = $recaptchaVerifier;
        $this->codeGenerator = $codeGenerator;
    }

    public function processLogin(
        string $email,
        string $password,
        ?string $recaptchaToken,
        SessionInterface $session
    ): LoginOutcome {
        if (!$this->recaptchaVerifier->verify($recaptchaToken)) {
            return LoginOutcome::redirect(
                'app_login',
                'error_login',
                'Veuillez cocher la case "Je ne suis pas un robot".'
            );
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return LoginOutcome::none('error_login', 'Identifiants incorrects.');
        }

        if ($user->getStatutCompte() === 'EN_ATTENTE') {
            return LoginOutcome::redirect(
                'app_login',
                'error_login',
                'Votre compte pro est en cours de validation par un administrateur.'
            );
        }

        if ($user->getStatutCompte() === 'REJETE') {
            return LoginOutcome::redirect(
                'app_login',
                'error_login',
                'Votre demande de compte a été refusée.'
            );
        }

        if (in_array($email, self::ADMIN_EMAILS, true)) {
            $session->set('2fa_user_id', $user->getId());

            return LoginOutcome::render(self::LOGIN_TEMPLATE, [
                'require_voice_2fa' => true,
                'last_username' => $email,
                'error' => null,
            ]);
        }

        $method = $user->getAuthMethod();

        if ($method === 'EMAIL') {
            $code2FA = $this->codeGenerator->generate();
            $session->set('2fa_user_id', $user->getId());
            $session->set('2fa_code', $code2FA);

            $emailMessage = (new Email())
                ->from('liontn2004@gmail.com')
                ->to($user->getEmail())
                ->subject('Votre code de connexion 2FA - Intel_link')
                ->html(
                    "<h2 style='color:#2F80ED;'>Intel_link - Securite</h2>".
                    "<p>Bonjour,</p>".
                    "<p>Une tentative de connexion a ete detectee sur votre compte.</p>".
                    "<p>Voici votre code de verification : <b style='font-size: 24px; letter-spacing: 4px;'>{$code2FA}</b></p>"
                );
            $this->mailer->send($emailMessage);

            return LoginOutcome::render(self::LOGIN_TEMPLATE, [
                'require_email_2fa' => true,
                'user_email' => $user->getEmail(),
                'last_username' => $email,
                'error' => null,
            ]);
        }

        if ($method === 'FACE') {
            $session->set('2fa_user_id', $user->getId());

            return LoginOutcome::render(self::LOGIN_TEMPLATE, [
                'require_face_2fa' => true,
                'last_username' => $email,
                'error' => null,
            ]);
        }

        if ($method === 'VOICE') {
            $session->set('2fa_user_id', $user->getId());

            return LoginOutcome::render(self::LOGIN_TEMPLATE, [
                'require_voice_2fa' => true,
                'last_username' => $email,
                'error' => null,
            ]);
        }

        $this->security->login($user, 'security.authenticator.form_login.main');

        return LoginOutcome::redirect('app_redirect_user');
    }
}
