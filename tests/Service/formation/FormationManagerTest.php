<?php

namespace App\Tests\Service\formation;

use App\Entity\formation\Formation;
use App\Service\formation\FormationManager;
use PHPUnit\Framework\TestCase;

final class FormationManagerTest extends TestCase
{
    public function testValidFormation(): void
    {
        $formation = new Formation();
        $formation->setTitre('Symfony Avance');
        $formation->setUrlVideo('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $manager = new FormationManager();

        $this->assertTrue($manager->validate($formation));
    }

    public function testFormationWithoutTitre(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre est obligatoire');

        $formation = new Formation();
        $formation->setTitre('');
        $formation->setUrlVideo('https://example.com/video');

        $manager = new FormationManager();
        $manager->validate($formation);
    }

    public function testFormationWithShortTitre(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir au moins 3 caracteres');

        $formation = new Formation();
        $formation->setTitre('AI');
        $formation->setUrlVideo('https://example.com/video');

        $manager = new FormationManager();
        $manager->validate($formation);
    }

    public function testFormationWithoutUrlVideo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("L'URL video est obligatoire");

        $formation = new Formation();
        $formation->setTitre('Formation DevOps');
        $formation->setUrlVideo('');

        $manager = new FormationManager();
        $manager->validate($formation);
    }

    public function testFormationWithInvalidUrlVideo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('URL video invalide');

        $formation = new Formation();
        $formation->setTitre('Formation DevOps');
        $formation->setUrlVideo('url_invalide');

        $manager = new FormationManager();
        $manager->validate($formation);
    }
}
