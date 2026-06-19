<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Permission;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('username', TextType::class, [
                'attr' => ['placeholder' => 'Enter username']
            ])
            ->add('firstname', TextType::class, [
                'attr' => ['placeholder' => 'Enter first name']
            ])
            ->add('lastname', TextType::class, [
                'attr' => ['placeholder' => 'Enter last name']
            ])
            ->add('email', EmailType::class, [
                'attr' => ['placeholder' => 'user@example.com']
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $isEdit ? 'New Password' : 'Password',
                'mapped' => false,
                'required' => !$isEdit,
                'attr' => ['placeholder' => $isEdit ? 'Leave blank to keep current' : 'Enter password'],
                'constraints' => !$isEdit ? [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        'max' => 4096,
                    ])
                ] : [],
            ])
            ->add('address', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 2, 'placeholder' => 'Address']
            ])
            ->add('note', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 2, 'placeholder' => 'Additional notes...']
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'Profile Picture',
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
            ->add('isAdmin', CheckboxType::class, [
                'label' => 'Grant Administrator Privileges',
                'required' => false,
                'mapped' => false,
            ])
            ->add('permissions', EntityType::class, [
                'class' => Permission::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'mapped' => false,
                'label' => 'Assign Scoped Permissions',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}
