<?php

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class SupplierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['placeholder' => 'Enter supplier name']
            ])
            ->add('phone', TelType::class, [
                'required' => false,
                'attr' => ['placeholder' => '+1 (555) 000-0000']
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'supplier@example.com']
            ])
            ->add('website', UrlType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'https://example.com']
            ])
            ->add('address', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Company headquarters, city, etc.']
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'Profile Avatar',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid JPEG, PNG or WebP image',
                    ])
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}
