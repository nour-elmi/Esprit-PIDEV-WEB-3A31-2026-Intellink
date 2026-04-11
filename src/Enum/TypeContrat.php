<?php
namespace App\Enum;

enum TypeContrat: string
{
    case CDI = 'CDI';
    case CDD = 'CDD';
    case STAGE = 'Stage';
    case FREELANCE = 'Freelance';

    public function getLabel(): string
    {
        return match($this) {
            self::CDI => 'Contrat à Durée Indéterminée',
            self::CDD => 'Contrat à Durée Déterminée',
            self::STAGE => 'Stage PFE / Été',
            self::FREELANCE => 'Mission Freelance',
        };
    }
}
