<?php

namespace App\Service;

use App\Entity\Post;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailService
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')]
        private string $fromEmail
    ) {
    }

    public function send(string $to, string $subject, string $html): void
    {
        $email = (new Email())
            ->from($this->fromEmail)
            ->to($to)
            ->subject($subject)
            ->text('Test Symfony Mail')
            ->html($html);

        $this->mailer->send($email);
    }

    public function sendPostHiddenEmail(User $user, Post $post): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to((string) $user->getEmail())
            ->subject('Votre publication a ete masquee')
            ->htmlTemplate('emails/post_hidden.html.twig')
            ->context([
                'user' => $user,
                'post' => $post,
            ]);

        $this->mailer->send($email);
    }

    public function sendPostLockedEmail(User $user, Post $post): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to((string) $user->getEmail())
            ->subject('Votre publication a ete verrouillee')
            ->htmlTemplate('emails/post_locked.html.twig')
            ->context([
                'user' => $user,
                'post' => $post,
            ]);

        $this->mailer->send($email);
    }
}
