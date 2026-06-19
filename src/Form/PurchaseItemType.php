<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\PurchaseItem;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PurchaseItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => 'name',
                'placeholder' => 'Select product...',
                'attr' => ['class' => 'product-select']
            ])
            ->add('quantity', IntegerType::class, [
                'attr' => ['min' => 1, 'class' => 'quantity-input']
            ])
            ->add('price', NumberType::class, [
                'label' => 'Cost Price',
                'attr' => ['class' => 'price-input']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PurchaseItem::class,
        ]);
    }
}
