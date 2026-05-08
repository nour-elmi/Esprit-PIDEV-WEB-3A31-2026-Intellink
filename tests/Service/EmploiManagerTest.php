<?php
namespace App\Tests\Service;

use App\Entity\Emploi;
use App\Service\EmploiManager;
use PHPUnit\Framework\TestCase;

class EmploiManagerTest extends TestCase
{
    // 1. Test de succès global (Donnée en entrée valide -> Test réussi)
    public function testOffreValide()
    {
        $emploi = new Emploi();
        $emploi->setTitre('Développeur Web');
        $emploi->setSalaire(3500);
        
        // Dates cohérentes pour la règle 3
        $dateDebut = new \DateTime();
        $dateFin = (new \DateTime())->modify('+1 month');
        $emploi->setDateDebut($dateDebut);
        $emploi->setDateExpiration($dateFin);

        $manager = new EmploiManager();
        
        // On vérifie que la validation renvoie true
        $this->assertTrue($manager->validateOffre($emploi));
    }

    // 2. Test de la règle 1 : Titre manquant (Test échoué attendu)
    public function testOffreSansTitreLanceException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre est obligatoire');

        $emploi = new Emploi();
        $emploi->setTitre(''); // Titre vide

        $manager = new EmploiManager();
        $manager->validateOffre($emploi);
    }

    // 3. Test de la règle 2 : Salaire négatif
    public function testSalaireNegatifLanceException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le salaire proposé doit être supérieur à zéro');

        $emploi = new Emploi();
        $emploi->setTitre('Test');
        $emploi->setSalaire(-500); // Salaire invalide

        $manager = new EmploiManager();
        $manager->validateOffre($emploi);
    }

    // 4. Test de la règle 3 : Chronologie des dates inversée[cite: 1]
    public function testDatesIncoherentesLanceException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date d\'expiration doit être postérieure à la date de création');

        $emploi = new Emploi();
        $emploi->setTitre('Test');
        
        $dateDebut = new \DateTime('2026-01-01');
        $dateFin = new \DateTime('2025-01-01'); // Date de fin avant le début[cite: 1]
        
        $emploi->setDateDebut($dateDebut);
        $emploi->setDateExpiration($dateFin);

        $manager = new EmploiManager();
        $manager->validateOffre($emploi);
    }
}