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
            ->add('description', null, [
                'attr' => ['class' => 'form-control-custom', 'placeholder' => 'Décrivez le poste...']
            ])
            ->add('salaire', null, [
                'attr' => ['class' => 'form-control-custom', 'placeholder' => 'Ex: 2500']
            ])
            ->add('date_debut', null, [
                'widget' => 'single_text', // Utilise le sélecteur de date natif du navigateur
                'attr' => ['class' => 'form-control-custom']
            ])
            ->add('date_expiration', null, [
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control-custom']
            ])
            ->add('type_contrat', EnumType::class, [
                'class' => TypeContrat::class,
                'multiple' => false,  
                'expanded' => false,  
                'choice_label' => fn ($choice) => $choice->value,
                'attr' => ['class' => 'form-control-custom']
            ])
            ->add('statut', EnumType::class, [
                'class' => Statut::class,
                'multiple' => false, 
                'expanded' => false, 
                'choice_label' => fn ($choice) => $choice->value,
                'attr' => ['class' => 'form-control-custom']
            ])
            ->add('nom_entreprise', null, [
                'attr' => ['class' => 'form-control-custom']
            ]);
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
