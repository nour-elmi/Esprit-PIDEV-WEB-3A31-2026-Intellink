<?php

namespace App\Tests\Service;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\CodeGeneratorInterface;
use App\Service\LoginOutcome;
use App\Service\LoginService;
use App\Service\RecaptchaVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class LoginServiceTest extends TestCase
{
    private UtilisateurRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private MailerInterface $mailer;
    private Security $security;
    private RecaptchaVerifier $recaptchaVerifier;
    private CodeGeneratorInterface $codeGenerator;
    private SessionInterface $session;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UtilisateurRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->recaptchaVerifier = $this->createMock(RecaptchaVerifier::class);
        $this->codeGenerator = $this->createMock(CodeGeneratorInterface::class);
        $this->session = $this->createMock(SessionInterface::class);
    }

    // Recaptcha invalid -> redirect to login with error.
    public function testRecaptchaInvalidRedirects(): void
    {
        $this->recaptchaVerifier
            ->expects($this->once())
            ->method('verify')
            ->with('token')
            ->willReturn(false);

        $this->userRepository->expects($this->never())->method('findOneBy');

        $service = $this->buildService();
        $outcome = $service->processLogin('user@test.tn', 'secret', 'token', $this->session);

        $this->assertSame(LoginOutcome::ACTION_REDIRECT, $outcome->getAction());
        $this->assertSame('app_login', $outcome->getRedirectRoute());
        $this->assertSame('error_login', $outcome->getFlashType());
    }

    // Invalid credentials -> no redirect, flash error message.
    public function testInvalidCredentialsAddsFlashWithoutRedirect(): void
    {
        $user = $this->createUser('user@test.tn', 'EMAIL');

        $this->recaptchaVerifier->method('verify')->willReturn(true);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->passwordHasher->method('isPasswordValid')->willReturn(false);

        $service = $this->buildService();
        $outcome = $service->processLogin('user@test.tn', 'wrong', 'token', $this->session);

        $this->assertSame(LoginOutcome::ACTION_NONE, $outcome->getAction());
        $this->assertSame('Identifiants incorrects.', $outcome->getFlashMessage());
    }

    // Pending account -> redirect to login with error.
    public function testAccountPendingRedirects(): void
    {
        $user = $this->createUser('user@test.tn', 'EMAIL');
        $user->setStatutCompte('EN_ATTENTE');

        $this->recaptchaVerifier->method('verify')->willReturn(true);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);

        $service = $this->buildService();
        $outcome = $service->processLogin('user@test.tn', 'secret', 'token', $this->session);

        $this->assertSame(LoginOutcome::ACTION_REDIRECT, $outcome->getAction());
        $this->assertSame('app_login', $outcome->getRedirectRoute());
        $this->assertSame('error_login', $outcome->getFlashType());
    }

    // Admin email -> require voice 2FA and render template.
    public function testAdminEmailRequiresVoice2FA(): void
    {
        $user = $this->createUser('admin@intel-link.com', 'FACE');

        $this->recaptchaVerifier->method('verify')->willReturn(true);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);

        $this->session
            ->expects($this->once())
            ->method('set')
            ->with('2fa_user_id', $user->getId());

        $this->mailer->expects($this->never())->method('send');
        $this->security->expects($this->never())->method('login');

        $service = $this->buildService();
        $outcome = $service->processLogin('admin@intel-link.com', 'secret', 'token', $this->session);

        $this->assertSame(LoginOutcome::ACTION_RENDER, $outcome->getAction());
        $this->assertTrue($outcome->getTemplateParams()['require_voice_2fa']);
    }

    // Email 2FA -> generate code, store in session, send mail.
    public function testEmail2FASendsCodeAndEmail(): void
    {
        $user = $this->createUser('user@test.tn', 'EMAIL');

        $this->recaptchaVerifier->method('verify')->willReturn(true);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->codeGenerator->method('generate')->willReturn('123');

        $sessionCalls = [];
        $this->session
            ->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function (string $key, mixed $value) use (&$sessionCalls): void {
                $sessionCalls[] = [$key, $value];
            });

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Email::class));

        $service = $this->buildService();
        $outcome = $service->processLogin('user@test.tn', 'secret', 'token', $this->session);

        $this->assertSame(LoginOutcome::ACTION_RENDER, $outcome->getAction());
        $this->assertTrue($outcome->getTemplateParams()['require_email_2fa']);
        $this->assertSame(
            [['2fa_user_id', $user->getId()], ['2fa_code', '123456']],
            $sessionCalls
        );
    }

    // Standard login -> call security login and redirect.
    public function testStandardLoginCallsSecurity(): void
    {
        $user = $this->createUser('user@test.tn', 'NONE');

        $this->recaptchaVerifier->method('verify')->willReturn(true);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);

        $this->security
            ->expects($this->once())
            ->method('login')
            ->with($user, 'security.authenticator.form_login.main');

        $service = $this->buildService();
        $outcome = $service->processLogin('user@test.tn', 'secret', 'token', $this->session);

        $this->assertSame(LoginOutcome::ACTION_REDIRECT, $outcome->getAction());
        $this->assertSame('app_redirect_user', $outcome->getRedirectRoute());
    }

    private function buildService(): LoginService
    {
        return new LoginService(
            $this->userRepository,
            $this->passwordHasher,
            $this->mailer,
            $this->security,
            $this->recaptchaVerifier,
            $this->codeGenerator
        );
    }

    private function createUser(string $email, string $authMethod): Utilisateur
    {
        $user = new Utilisateur();
        $user->setId(10);
        $user->setEmail($email);
        $user->setAuthMethod($authMethod);
        $user->setStatutCompte('ACTIF');

        return $user;
    }
}
