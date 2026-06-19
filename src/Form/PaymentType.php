<?php

namespace App\Form;

use App\Entity\Payment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', NumberType::class, [
                'label' => 'Payment Amount ($)',
                'attr' => ['placeholder' => 'e.g. 500.00', 'step' => '0.01']
            ])
            ->add('method', ChoiceType::class, [
                'label' => 'Payment Method',
                'choices' => [
                    'Cash' => 'Cash',
                    'Bank Transfer' => 'Bank Transfer',
                    'Stripe' => 'Stripe',
                    'Credit Card' => 'Credit Card',
                    'Other' => 'Other',
                ],
                'placeholder' => 'Select a method...',
            ])
            ->add('reference', TextType::class, [
                'label' => 'Transaction Reference',
                'required' => false,
                'attr' => ['placeholder' => 'Check #, TX ID, etc.']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
        ]);
    }
}
