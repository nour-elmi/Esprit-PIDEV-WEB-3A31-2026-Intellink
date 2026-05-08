<?php

namespace App\Service;

use App\Entity\Emploi;

class CVGenerator
{
    public function canGenerateCV(Emploi $emploi): bool
    {
        if (trim((string) $emploi->getDescription()) === '') {
            throw new \InvalidArgumentException("L'offre doit contenir une description pour generer un CV");
        }

        return true;
    }
}
