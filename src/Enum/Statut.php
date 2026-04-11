<?php
namespace App\Enum;

enum Statut: string
{
    case ouverte = 'ouverte';
    case fermee = 'fermee';

    public function getLabel(): string
    {
        return match($this) {
            self::ouverte => 'offre ouverte',
            self::fermee => 'offre fermée',
        };
    }
}
