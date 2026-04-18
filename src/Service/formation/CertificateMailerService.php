<?php

namespace App\Service\formation;

use App\Entity\Utilisateur;
use App\Entity\formation\Formation;
use Nucleos\DompdfBundle\Factory\DompdfFactoryInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CertificateMailerService
{
    public function __construct(
        private readonly DompdfFactoryInterface $dompdfFactory,
        private readonly Environment $twig,
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    public function sendQuizCertificate(
        Utilisateur $user,
        Formation $formation,
        float $score,
        float $scoreMax
    ): void {
        $logoDataUri = $this->buildLogoDataUri();

        $html = $this->twig->render('formation/pdf/certificate.html.twig', [
            'nom' => $user->getNom(),
            'email' => $user->getEmail(),
            'formation' => $formation,
            'score' => $score,
            'scoreMax' => $scoreMax,
            'logoDataUri' => $logoDataUri,
            'generatedAt' => new \DateTimeImmutable(),
            'certificateRef' => sprintf(
                'CERT-%d-%d-%s',
                (int) ($formation->getIdFormation() ?? 0),
                (int) ($user->getId() ?? 0),
                (new \DateTimeImmutable())->format('YmdHis')
            ),
        ]);

        $dompdf = $this->dompdfFactory->create();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $pdf = $dompdf->output();

        $safeFormation = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($formation->getTitre() ?? 'formation')) ?: 'formation';
        $filename = 'certificat_' . $safeFormation . '.pdf';

        $from = (string) ($_ENV['MAILER_FROM'] ?? 'liontn2004@gmail.com');

        $email = (new Email())
            ->from($from)
            ->to((string) $user->getEmail())
            ->subject('Certificat de reussite - ' . (string) $formation->getTitre())
            ->html($this->twig->render('formation/mail/certificate_email.html.twig', [
                'nom' => $user->getNom(),
                'formation' => $formation,
                'score' => $score,
                'scoreMax' => $scoreMax,
            ]))
            ->attach($pdf, $filename, 'application/pdf');

        $this->mailer->send($email);
    }

    private function buildLogoDataUri(): ?string
    {
        $projectDir = (string) $this->parameterBag->get('kernel.project_dir');
        $logoPath = $projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo.png';

        if (!is_file($logoPath)) {
            return null;
        }

        $binary = @file_get_contents($logoPath);
        if ($binary === false || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($binary);
    }
}
