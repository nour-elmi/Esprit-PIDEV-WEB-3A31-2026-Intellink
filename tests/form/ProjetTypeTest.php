<?php

namespace App\Tests\Form;

use App\Entity\Projet;
use App\Form\ProjetType;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Validator\Validation;

class ProjetTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension([new ProjetType()], []),
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    public function testSubmitValidDataMapsToProjet(): void
    {
        $formData = [
            'titre' => 'Application mobile',
            'description' => 'Application pour suivi de projets collaboratifs.',
            'statut' => 'en cours',
            'prix' => '2500.75',
        ];

        $model = new Projet();
        $form = $this->factory->create(ProjetType::class, $model);

        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        self::assertSame('Application mobile', $model->getTitre());
        self::assertSame('Application pour suivi de projets collaboratifs.', $model->getDescription());
        self::assertSame('en cours', $model->getStatut());
        self::assertSame(2500.75, $model->getPrix());
    }

    public function testSubmitInvalidBlankTitleIsNotValid(): void
    {
        $formData = [
            'titre' => '   ',
            'description' => 'Description valide avec plus de dix caracteres.',
            'statut' => 'en attente',
            'prix' => '100',
        ];

        $form = $this->factory->create(ProjetType::class, new Projet());
        $form->submit($formData);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('titre')->getErrors(true)->count());
    }

    public function testSubmitInvalidNegativePriceIsNotValid(): void
    {
        $formData = [
            'titre' => 'Projet IA',
            'description' => 'Description valide avec plus de dix caracteres.',
            'statut' => 'en cours',
            'prix' => '-10',
        ];

        $form = $this->factory->create(ProjetType::class, new Projet());
        $form->submit($formData);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('prix')->getErrors(true)->count());
    }

    public function testSubmitInvalidTooShortDescriptionIsNotValid(): void
    {
        $formData = [
            'titre' => 'Projet Web',
            'description' => 'Court',
            'statut' => 'en cours',
            'prix' => '100',
        ];

        $form = $this->factory->create(ProjetType::class, new Projet());
        $form->submit($formData);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('description')->getErrors(true)->count());
    }

    public function testSubmitInvalidStatusChoiceIsNotValid(): void
    {
        $formData = [
            'titre' => 'Projet Mobile',
            'description' => 'Description valide avec plus de dix caracteres.',
            'statut' => 'archive',
            'prix' => '500',
        ];

        $form = $this->factory->create(ProjetType::class, new Projet());
        $form->submit($formData);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('statut')->getErrors(true)->count());
    }
}
