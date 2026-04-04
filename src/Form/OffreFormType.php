<?php

namespace App\Form;

use App\Entity\OffreEmploi;
use App\Entity\TypeContrat; 
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use App\Entity\Statut; 
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OffreFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            //->add('id_offre')
            ->add('description')
            ->add('salaire')
            ->add('date_debut')
            ->add('date_expiration')
            ->add('type_contrat', EnumType::class, [
                'class' => TypeContrat::class,
                'multiple' => true,  
                'expanded' => false,  
                'choice_label' => fn ($choice) => $choice->value,
            ])
            ->add('statut', EnumType::class, [
                'class' => Statut::class,
                'multiple' => false,  
                'expanded' => false,  
                'choice_label' => fn ($choice) => $choice->value,
            ])
            ->add('nom_entreprise')
            //->add('id_user')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OffreEmploi::class,
        ]);
    }
}
