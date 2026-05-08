<?php
namespace App\Tests\Service;

use App\Entity\Emploi;
use App\Service\CVGenerator;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase {
    public function testSuccessfulCVGeneration() {
        $emploi = new Emploi();
        $emploi->setDescription('PHP, Symfony, Git');
        
        $generator = new CVGenerator();
        $this->assertTrue($generator->canGenerateCV($emploi)); // Doit réussir
    }

    public function testFailedCVGenerationWithoutSkills() {
        $this->expectException(\InvalidArgumentException::class); // On attend une erreur
        
        $emploi = new Emploi();
        $emploi->setDescription(''); // description vide
        
        $generator = new CVGenerator();
        $generator->canGenerateCV($emploi);
    }
}
