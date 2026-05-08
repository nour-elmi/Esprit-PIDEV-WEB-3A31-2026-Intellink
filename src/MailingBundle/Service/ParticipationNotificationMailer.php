<?php

namespace App\MailingBundle\Service;

use App\Entity\Collaboration;
use App\Entity\ContratParticipation;
use App\Entity\Utilisateur;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class ParticipationNotificationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer
    ) {
    }

    public function notifyChefForNewParticipation(
        Collaboration $demande,
        Utilisateur $demandeur,
        ?Utilisateur $chef = null
    ): bool {
        $projet = $demande->getProjet();
        if ($projet === null) {
            return false;
        }

        $chefEmail = $this->resolveChefEmail($projet->getCreateur(), $chef);
        if ($chefEmail === null) {
            return false;
        }

        $chefNom = trim((string) ($chef?->getNom() ?? ''));
        if ($chefNom === '') {
            $chefNom = 'Chef de projet';
        }

        $fromEmail = $this->resolveFromEmail();
        $fromName = $this->resolveFromName();

        $email = (new TemplatedEmail())
            ->from(new Address($fromEmail, $fromName))
            ->to(new Address($chefEmail, $chefNom))
            ->subject('Nouvelle demande de participation - ' . ((string) ($projet->getTitre() ?? 'Projet')))
            ->htmlTemplate('emails/participation_request.html.twig')
            ->context([
                'chefNom' => $chefNom,
                'projet' => $projet,
                'demande' => $demande,
                'demandeur' => $demandeur,
            ]);

        $this->mailer->send($email);

        return true;
    }

    public function notifyUserToSignContract(
        Collaboration $demande,
        ContratParticipation $contrat,
        Utilisateur $demandeur,
        ?Utilisateur $chef = null,
        ?string $contractUrl = null
    ): bool {
        $projet = $demande->getProjet();
        if ($projet === null) {
            return false;
        }

        $userEmail = trim((string) $demandeur->getEmail());
        if (!$this->isValidEmail($userEmail)) {
            return false;
        }

        $userNom = trim((string) $demandeur->getNom());
        if ($userNom === '') {
            $userNom = 'Participant';
        }

        $chefNom = trim((string) ($chef?->getNom() ?? ''));
        if ($chefNom === '') {
            $chefNom = 'Chef de projet';
        }

        $fromEmail = $this->resolveFromEmail();
        $fromName = $this->resolveFromName();

        $email = (new TemplatedEmail())
            ->from(new Address($fromEmail, $fromName))
            ->to(new Address($userEmail, $userNom))
            ->subject('Contrat disponible - signature requise')
            ->htmlTemplate('emails/contract_signature_required.html.twig')
            ->context([
                'userNom' => $userNom,
                'chefNom' => $chefNom,
                'projet' => $projet,
                'demande' => $demande,
                'contrat' => $contrat,
                'contractUrl' => $contractUrl,
            ]);

        $this->mailer->send($email);

        return true;
    }

    private function resolveChefEmail(?string $createur, ?Utilisateur $chef): ?string
    {
        $candidate = trim((string) ($chef?->getEmail() ?? ''));
        if ($this->isValidEmail($candidate)) {
            return $candidate;
        }

        $candidate = trim((string) $createur);
        if ($this->isValidEmail($candidate)) {
            return $candidate;
        }

        return null;
    }

    private function resolveFromEmail(): string
    {
        $fromEmail = (string) (
            $_ENV['MAILING_FROM_EMAIL']
            ?? $_SERVER['MAILING_FROM_EMAIL']
            ?? getenv('MAILING_FROM_EMAIL')
        );

        if ($this->isValidEmail($fromEmail)) {
            return $fromEmail;
        }

        $mailerFrom = (string) (
            $_ENV['MAILER_FROM']
            ?? $_SERVER['MAILER_FROM']
            ?? getenv('MAILER_FROM')
        );

        if ($this->isValidEmail($mailerFrom)) {
            return $mailerFrom;
        }

        return 'no-reply@localhost';
    }

    private function resolveFromName(): string
    {
        $name = trim((string) (
            $_ENV['MAILING_FROM_NAME']
            ?? $_SERVER['MAILING_FROM_NAME']
            ?? getenv('MAILING_FROM_NAME')
        ));

        return $name !== '' ? $name : 'PiDev';
    }

    private function isValidEmail(string $value): bool
    {
        return filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
    }
}
