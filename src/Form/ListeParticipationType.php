<?php

namespace App\Form;

use App\Entity\ListeParticipation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;

class ListeParticipationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder
        ->add('nom_p')
        ->add('prenom_p')
        ->add('cv', FileType::class, [
            'label' => 'Votre CV (Fichier PDF)',
            'mapped' => true,
            'required' => true,
            'constraints' => [
                new File([
                    'maxSize' => '2M',
                    'mimeTypes' => [
                        'application/pdf',
                        'application/x-pdf',
                    ],
                    'mimeTypesMessage' => 'Veuillez uploader un document PDF valide',
                ])
            ],
            'attr' => ['class' => 'form-control-custom']
        ])
        ->add('skills')
        
    ;
}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ListeParticipation::class,
        ]);
    }
}