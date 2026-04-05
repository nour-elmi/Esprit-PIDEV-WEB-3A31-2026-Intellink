<?php

namespace App\Entity;

enum Statutt: string
{
    case EN_ATTENTE = 'en_attente';
    case acceptee = 'acceptee';
    case refusee = 'refusee';
    
}