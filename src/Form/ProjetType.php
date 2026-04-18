<?php

namespace App\Form;

use App\Entity\Projet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProjetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre du projet',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le titre est obligatoire.',
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) {
                        $value = (string) $value;

                        if (trim($value) === '') {
                            $context->buildViolation('Le titre ne doit pas contenir uniquement des espaces.')
                                ->addViolation();
                        }
                    }),
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères.',
                        'max' => 200,
                        'maxMessage' => 'Le titre ne doit pas dépasser {{ limit }} caractères.',
                    ]),
                    new Regex([
                        'pattern' => '/^[\p{L}\p{N}\s\-\_\.\,\(\)\'"]+$/u',
                        'message' => 'Le titre contient des caractères non autorisés.',
                    ]),
                ],
                'attr' => [
                    'minlength' => 3,
                    'maxlength' => 200,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'La description est obligatoire.',
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) {
                        $value = (string) $value;

                        if (trim($value) === '') {
                            $context->buildViolation('La description ne doit pas contenir uniquement des espaces.')
                                ->addViolation();
                        }
                    }),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.',
                        'max' => 2000,
                        'maxMessage' => 'La description ne doit pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr' => [
                    'minlength' => 10,
                    'maxlength' => 2000,
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'en attente',
                    'En cours' => 'en cours',
                    'Terminé' => 'termine',
                ],
                'placeholder' => 'Choisir un statut',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez choisir un statut.',
                    ]),
                ],
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Budget',
                'currency' => 'TND',
                'scale' => 2,
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le prix est obligatoire.',
                    ]),
                    new PositiveOrZero([
                        'message' => 'Le prix doit être positif ou nul.',
                    ]),
                ],
                'attr' => [
                    'min' => '0',
                    'step' => '0.01',
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Projet::class,
        ]);
    }
}