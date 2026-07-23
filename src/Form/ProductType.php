<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Product Name',
                'attr' => ['placeholder' => 'e.g. Premium Subscription, Wireless Mouse...']
            ])
            ->add('sku', TextType::class, [
                'label' => 'Product SKU',
                'required' => false,
                'attr' => ['placeholder' => 'e.g. PRD-001']
            ])
            ->add('price', NumberType::class, [
                'label' => 'Selling Price ($)',
                'scale' => 2,
                'attr' => ['placeholder' => '0.00']
            ])
            ->add('purchasePrice', NumberType::class, [
                'label' => 'Cost / Purchase Price ($)',
                'scale' => 2,
                'required' => false,
                'attr' => ['placeholder' => '0.00']
            ])
            ->add('stockQuantity', NumberType::class, [
                'label' => 'Initial Stock Level',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['placeholder' => '0']
            ])
            ->add('isSerialized', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'label' => 'Track as Individual Items (Serial Numbers / Unique Codes)',
                'required' => false,
                'help' => 'Enable this for laptops, phones, books or items requiring individual serial numbers and status tracking.'
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Product Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Add some notes or details about the product...',
                    'rows' => 4,
                    'style' => 'width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-family: inherit; font-size: 14px; outline: none; transition: border-color 0.2s;'
                ]
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Main Product Photo',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file (JPEG, PNG, WebP or GIF)',
                    ])
                ],
            ])
            ->add('screenFiles', FileType::class, [
                'label' => 'Additional Screenshots',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'attr' => [
                    'multiple' => 'multiple',
                    'accept' => 'image/*',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
