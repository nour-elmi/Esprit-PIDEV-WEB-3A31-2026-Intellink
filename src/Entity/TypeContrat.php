<?php

namespace App\Entity;

enum TypeContrat: string
{
    case CDI = 'CDI';
    case CDD = 'CDD';
    case FREELANCE = 'Freelance';
    case STAGE = 'Stage';
}