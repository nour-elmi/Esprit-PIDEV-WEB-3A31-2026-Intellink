<?php

namespace App\Form;

use App\Entity\Emploi;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Enum\TypeContrat;
use App\Enum\Statut;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

class EmploiType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre')
            ->add('description')
            ->add('TypeContrat', EnumType::class, [
                'class' => TypeContrat::class,
                'choice_label' => fn (TypeContrat $choice) => $choice->getLabel(),
                'label' => 'Type de contrat',
                'attr' => ['class' => 'form-control-custom']
            ])
            ->add('salaire')
            ->add('date_debut')
            ->add('date_expiration')
            ->add('Statut', EnumType::class, [
                'class' => Statut::class,
                'choice_label' => fn (Statut $choice) => $choice->getLabel(),
                'label' => 'statut',
                'attr' => ['class' => 'form-control-custom']
            ])
            ->add('nom_entreprise')
            ->add('id_user')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Emploi::class,
        ]);
    }
}
