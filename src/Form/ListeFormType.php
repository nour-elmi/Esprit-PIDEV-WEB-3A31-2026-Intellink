<?php

namespace App\Form;

use App\Entity\ListeParticipation;
use App\Entity\Statutt;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ListeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date_participation')
            ->add('statutt', EnumType::class, [
                'class' => Statutt::class,
                'choice_label' => fn (Statutt $choice) => match ($choice) {
                    Statutt::EN_ATTENTE => 'En_attente',
                    Statutt:: acceptee=> 'Acceptée',
                    Statutt::refusee => 'Refusée',
                },
                'label' => 'Statut de la demande'
            ])
            ->add('date_reponse')
            ->add('id_offre')
            ->add('id_user')
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
