<?php

namespace App\Form;

use App\Entity\Contact;
use App\Entity\Purchase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Form\PurchaseItemType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class PurchaseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isDoctor = $options['is_doctor'] ?? false;

        $builder
            ->add('contact', EntityType::class, [
                'class' => Contact::class,
                'choice_label' => 'name',
                'query_builder' => function (\Doctrine\ORM\EntityRepository $er) use ($isDoctor) {
                    $qb = $er->createQueryBuilder('c')->orderBy('c.name', 'ASC');
                    if ($isDoctor) {
                        $qb->where('c.type = :type')
                           ->setParameter('type', 'supplier');
                    }
                    return $qb;
                },
                'placeholder' => $isDoctor ? 'Select a supplier...' : 'Select Contact...',
                'label' => $isDoctor ? 'Vendor Supplier' : 'Contact',
            ])
            ->add('total', NumberType::class, [
                'label' => 'Total Cost ($)',
                'attr' => ['readonly' => true]
            ])
            ->add('created_at', null, [
                'widget' => 'single_text',
                'label' => 'Acquisition Date'
            ])
            ->add('purchaseItems', CollectionType::class, [
                'entry_type' => PurchaseItemType::class,
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
            'data_class' => Purchase::class,
            'is_doctor' => false,
        ]);
    }
}
