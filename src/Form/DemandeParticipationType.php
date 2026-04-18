<?php

namespace App\Form;

use App\Entity\Collaboration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class DemandeParticipationType extends AbstractType
{
    private const ROLES = [
        'Développeur full-stack' => 'Développeur full-stack',
        'Développeur front-end' => 'Développeur front-end',
        'Développeur back-end' => 'Développeur back-end',
        'UI/UX Designer' => 'UI/UX Designer',
        'Graphiste' => 'Graphiste',
        'Chef de projet' => 'Chef de projet',
        'Monteur vidéo' => 'Monteur vidéo',
        'Community manager' => 'Community manager',
        'Rédacteur' => 'Rédacteur',
        'Testeur QA' => 'Testeur QA',
        'Autre' => '__autre__',
    ];

    private const DISPONIBILITES = [
        '1 h par jour' => '1 h par jour',
        '2 h par jour' => '2 h par jour',
        '3 h par jour' => '3 h par jour',
        '4 h par jour' => '4 h par jour',
        'Quelques heures par semaine' => 'Quelques heures par semaine',
        'Week-end uniquement' => 'Week-end uniquement',
        'Flexible' => 'Flexible',
        'Soirs uniquement' => 'Soirs uniquement',
        'Autre' => '__autre__',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('roleSouhaite', ChoiceType::class, [
                'label' => 'Rôle souhaité',
                'choices' => self::ROLES,
                'expanded' => true,
                'multiple' => false,
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez choisir un rôle souhaité.',
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) {
                        $form = $context->getRoot();
                        $autreRole = trim((string) $form->get('autreRole')->getData());

                        if ($value === '__autre__' && $autreRole === '') {
                            $context->buildViolation('Veuillez préciser votre rôle.')
                                ->addViolation();
                        }
                    }),
                ],
                'choice_attr' => function ($choice, string $key, mixed $value): array {
                    return [
                        'class' => 'choice-input',
                        'data-choice-value' => $value,
                        'required' => 'required',
                    ];
                },
                'label_attr' => [
                    'class' => 'field-title',
                ],
            ])
            ->add('autreRole', TextType::class, [
                'label' => 'Précisez votre rôle',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control-custom conditional-input',
                    'placeholder' => 'Ex : Motion designer, Data analyst, Sound designer...',
                ],
            ])
            ->add('disponibilite', ChoiceType::class, [
                'label' => 'Disponibilité',
                'choices' => self::DISPONIBILITES,
                'expanded' => true,
                'multiple' => false,
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez choisir une disponibilité.',
                    ]),
                    new Callback(function ($value, ExecutionContextInterface $context) {
                        $form = $context->getRoot();
                        $autreDisponibilite = trim((string) $form->get('autreDisponibilite')->getData());

                        if ($value === '__autre__' && $autreDisponibilite === '') {
                            $context->buildViolation('Veuillez préciser votre disponibilité.')
                                ->addViolation();
                        }
                    }),
                ],
                'choice_attr' => function ($choice, string $key, mixed $value): array {
                    return [
                        'class' => 'choice-input',
                        'data-choice-value' => $value,
                        'required' => 'required',
                    ];
                },
                'label_attr' => [
                    'class' => 'field-title',
                ],
            ])
            ->add('autreDisponibilite', TextType::class, [
                'label' => 'Précisez votre disponibilité',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control-custom conditional-input',
                    'placeholder' => 'Ex : 6 h par semaine, uniquement le soir, selon planning...',
                ],
            ])
            ->add('portfolio', UrlType::class, [
                'label' => 'Lien portfolio',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'class' => 'form-control-custom',
                    'placeholder' => 'https://mon-portfolio.com',
                ],
            ])
            ->add('motivation', TextareaType::class, [
                'label' => 'Motivation',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez renseigner votre motivation.',
                    ]),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'La motivation doit contenir au moins {{ limit }} caractères.',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control-custom',
                    'rows' => 6,
                    'placeholder' => 'Expliquez brièvement votre motivation pour rejoindre ce projet...',
                    'required' => 'required',
                    'minlength' => 10,
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Envoyer la demande',
                'attr' => [
                    'class' => 'btn-success-custom',
                ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $demande = $event->getData();
            $form = $event->getForm();

            if (!$demande instanceof Collaboration) {
                return;
            }

            $role = $demande->getRoleSouhaite();
            $disponibilite = $demande->getDisponibilite();

            if ($role !== null) {
                if ($this->isPredefinedRole($role)) {
                    $form->get('roleSouhaite')->setData($role);
                } else {
                    $form->get('roleSouhaite')->setData('__autre__');
                    $form->get('autreRole')->setData($role);
                }
            }

            if ($disponibilite !== null) {
                if ($this->isPredefinedDisponibilite($disponibilite)) {
                    $form->get('disponibilite')->setData($disponibilite);
                } else {
                    $form->get('disponibilite')->setData('__autre__');
                    $form->get('autreDisponibilite')->setData($disponibilite);
                }
            }
        });
    }

    private function isPredefinedRole(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array($value, array_values(self::ROLES), true) && $value !== '__autre__';
    }

    private function isPredefinedDisponibilite(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array($value, array_values(self::DISPONIBILITES), true) && $value !== '__autre__';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Collaboration::class,
        ]);
    }
}