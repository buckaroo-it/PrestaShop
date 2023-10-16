<?php

namespace Buckaroo\PrestaShop\Src\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class IdinTabType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $description = "To require iDIN for specific products, you'll also need to have iDIN to be enabled for specific products in the Buckaroo App. You can find this setting at the iDIN verification settings.";

        $builder
            ->add('idin_description', TextType::class, [
                'label' => false,
                'required' => false,
                'disabled' => true,
                'data' => $description,
                'attr' => [
                    'readonly' => 'readonly',
                    'style' => 'border: none; background: transparent;'
                ]
            ])
            ->add('buckaroo_idin', CheckboxType::class, [
                'label' => 'Require iDIN verification for this product.',
                'required' => false,
            ]);
    }
}