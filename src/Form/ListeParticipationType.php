<?php

namespace App\Form;

use App\Entity\Emploi;
use App\Entity\ListeParticipation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ListeParticipationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id_participation')
            ->add('date_participation')
            ->add('statut')
            ->add('date_reponse')
            ->add('id_user')
            ->add('nom_p')
            ->add('prenom_p')
            ->add('cv')
            ->add('skills')
            ->add('score')
            ->add('id_offre', EntityType::class, [
                'class' => Emploi::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ListeParticipation::class,
        ]);
    }
}
