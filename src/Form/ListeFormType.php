<?php

namespace App\Form;

use App\Entity\ListeParticipation;
use App\Entity\Statutt;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\OffreEmploi;

class ListeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date_participation')
            ->add('nom_p')
            ->add('prenom_p')
            ->add('cv')
            ->add('skills')
            ->add('score')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ListeParticipation::class,
        ]);
    }
}
