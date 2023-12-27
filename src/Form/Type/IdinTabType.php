<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this file
 *
 * @author    Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Buckaroo\PrestaShop\Src\Form\Type;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class IdinTabType extends TranslatorAwareType
{
    /**
     * @var \Currency
     */
    private $defaultCurrency;

    /**
     * @param TranslatorInterface $translator
     * @param array $locales
     * @param \Currency $defaultCurrency
     */
    public function __construct(
        TranslatorInterface $translator,
        array $locales,
        \Currency $defaultCurrency
    ) {
        parent::__construct($translator, $locales);
        $this->defaultCurrency = $defaultCurrency;
    }

    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        $description = $this->trans("To require iDIN for specific products, you'll also need to have iDIN to be enabled for specific products in the Buckaroo App. You can find this setting at the iDIN verification settings.", 'Modules.Buckaroo.Admin');
        $builder
            ->add('idin_description', TextType::class, [
                'label' => $description,
                'required' => false,
                'disabled' => true,
                'data' => false,
                'attr' => [
                    'style' => 'display:none;',
                ],
            ])
            ->add('buckaroo_idin', CheckboxType::class, [
                'label' => $this->trans('Require iDIN verification for this product.', 'Modules.Buckaroo.Admin'),
                'required' => false,
            ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults([
                'label' => $this->trans('iDIN Settings', 'Modules.Bucakroo.Admin'),
            ])
        ;
    }
}
