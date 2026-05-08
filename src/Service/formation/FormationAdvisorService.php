<?php

namespace App\Service\formation;

final class FormationAdvisorService
{
    // CHANGEMENT: suppression de la propriete injectee mais jamais lue.
    // Ancien code (garde):
    // public function __construct(
    //     private readonly HttpClientInterface $httpClient,
    // ) {}

    /**
     * @param array<string, string> $answers
     * @return array<string, mixed>
     */
    public function advise(array $answers): array
    {
        $q1 = trim((string) ($answers['q1'] ?? ''));
        $q2 = trim((string) ($answers['q2'] ?? ''));
        $q3 = trim((string) ($answers['q3'] ?? ''));
        $q4 = trim((string) ($answers['q4'] ?? ''));

        // Déduire le domaine — doit correspondre EXACTEMENT à data-domaine des cartes
        $d = mb_strtolower($q1);
        if (str_contains($d, 'ia') || str_contains($d, 'data')) {
            $domaine = 'ia';
        } elseif (str_contains($d, 'web')) {
            $domaine = 'informatique';
        } elseif (str_contains($d, 'cyber') || str_contains($d, 'réseau') || str_contains($d, 'reseau')) {
            $domaine = 'cybersécurité';
        } elseif (str_contains($d, 'devops') || str_contains($d, 'cloud')) {
            $domaine = 'devops';
        } else {
            $domaine = '';
        }

        // Déduire le niveau — doit correspondre EXACTEMENT à data-niveau des cartes
        $nRaw = mb_strtolower($q2);
        if (str_contains($nRaw, 'début') || str_contains($nRaw, 'debut')) {
            $niveau = 'débutant';
        } elseif (str_contains($nRaw, 'avancé') || str_contains($nRaw, 'avance')) {
            $niveau = 'avancé';
        } else {
            $niveau = 'intermédiaire';
        }

        // Construire le conseil
        $domaineLabel = $domaine ?: 'tous domaines';
        $advice = "Formations {$niveau} en {$domaineLabel} recommandées pour vous.";

        if (str_contains(mb_strtolower($q3), 'emploi')) {
            $advice .= ' Privilégiez les parcours pratiques pour renforcer votre employabilité.';
        } elseif (str_contains(mb_strtolower($q3), 'certification')) {
            $advice .= ' Orientez-vous vers des formations avec certificat.';
        } elseif (str_contains(mb_strtolower($q3), 'projet')) {
            $advice .= ' Choisissez des formations axées sur des projets concrets.';
        }

        if (str_contains(mb_strtolower($q4), 'peu')) {
            $advice .= ' Modules courts recommandés.';
        } elseif (str_contains(mb_strtolower($q4), 'élevé') || str_contains(mb_strtolower($q4), 'eleve')) {
            $advice .= ' Vous pouvez vous engager sur des parcours intensifs.';
        }

        return [
            'advice'  => $advice,
            'filters' => [
                'domaine' => $domaine,
                'niveau'  => $niveau,
            ],
        ];
    }
}
