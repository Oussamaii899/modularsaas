<?php

namespace App\Form;

use App\Entity\Contact;
use App\Entity\Sale;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Form\SaleItemType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class SaleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contact', EntityType::class, [
                'class' => Contact::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a client...',
                'label' => 'Customer Client',
            ])
            ->add('total', NumberType::class, [
                'label' => 'Total Amount ($)',
                'attr' => ['readonly' => true] 
            ])
            ->add('created_at', null, [
                'widget' => 'single_text',
                'label' => 'Transaction Date'
            ])
            ->add('saleItems', CollectionType::class, [
                'entry_type' => SaleItemType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sale::class,
        ]);
    }
}
