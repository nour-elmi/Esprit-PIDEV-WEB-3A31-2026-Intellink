<?php
namespace App\Enum;

enum stat: string
{
    case acceptee = 'acceptee';
    case refusee = 'refusee';
    case en_attente = 'en_attente';

    public function getLabel(): string
    {
        return match($this) {
            self::acceptee => 'participation acceptée',
            self::refusee => 'participation fermée',
            self::en_attente => 'participation en_attente',
        };
    }
}
