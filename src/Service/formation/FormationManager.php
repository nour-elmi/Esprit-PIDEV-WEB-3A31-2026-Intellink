<?php

namespace App\Service\formation;

use App\Entity\formation\Formation;

final class FormationManager
{
    public function validate(Formation $formation): bool
    {
        $titre = trim((string) $formation->getTitre());
        if ($titre === '') {
            throw new \InvalidArgumentException('Le titre est obligatoire');
        }

        if (mb_strlen($titre) < 3) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 3 caracteres');
        }

        $urlVideo = trim((string) $formation->getUrlVideo());
        if ($urlVideo === '') {
            throw new \InvalidArgumentException("L'URL video est obligatoire");
        }

        if (!filter_var($urlVideo, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('URL video invalide');
        }

        return true;
    }
}
