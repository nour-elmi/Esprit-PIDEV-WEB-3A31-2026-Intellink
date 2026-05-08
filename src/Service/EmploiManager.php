<?php
namespace App\Service;

use App\Entity\Emploi;

class EmploiManager
{
    /**
     * Cette fonction valide les règles métier d'une offre d'emploi
     */
    public function validateOffre(Emploi $emploi): bool
    {
        // Règle 1 : Le titre est obligatoire
        if (empty($emploi->getTitre())) {
            throw new \InvalidArgumentException('Le titre est obligatoire');
        }

        // Règle 2 : Le salaire doit être positif
        if ($emploi->getSalaire() !== null && $emploi->getSalaire() <= 0) {
            throw new \InvalidArgumentException('Le salaire proposé doit être supérieur à zéro');
        }

        // Règle 3 : Chronologie des dates (Date Expiration > Date Création)
        if ($emploi->getDateExpiration() && $emploi->getDateDebut()) {
            if ($emploi->getDateExpiration() <= $emploi->getDateDebut()) {
                throw new \InvalidArgumentException('La date d\'expiration doit être postérieure à la date de création');
            }
        }

        return true;
    }
}