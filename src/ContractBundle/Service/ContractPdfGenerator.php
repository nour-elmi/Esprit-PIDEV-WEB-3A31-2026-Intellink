<?php

namespace App\ContractBundle\Service;

use App\Entity\Collaboration;
use App\Entity\ContratParticipation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

class ContractPdfGenerator
{
    public function __construct(
        private readonly Environment $twig,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
    ) {
    }

    public function generateSignedContractPdf(ContratParticipation $contrat, Collaboration $demande): string
    {
        $supportsPng = $this->supportsPngRendering();

        $html = $this->twig->render('contract/pdf_signed_contract.html.twig', [
            'contrat' => $contrat,
            'demande' => $demande,
            'signatureDataUri' => $supportsPng ? $this->buildSignatureDataUri($contrat) : null,
            'signatureRenderingDisabled' => !$supportsPng,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        // Use a built-in PDF font to avoid font parsing issues on some local PHP/iconv setups.
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function buildSignatureDataUri(ContratParticipation $contrat): ?string
    {
        $signature = trim((string) ($contrat->getUserSignatureName() ?? ''));
        if ($signature === '') {
            return null;
        }

        $relative = ltrim($signature, '/');
        if (!str_starts_with($relative, 'uploads/signatures/')) {
            return null;
        }

        $absolutePath = $this->projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (!is_file($absolutePath)) {
            return null;
        }

        $binary = @file_get_contents($absolutePath);
        if ($binary === false || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($binary);
    }

    private function supportsPngRendering(): bool
    {
        return function_exists('imagecreatefrompng');
    }
}
