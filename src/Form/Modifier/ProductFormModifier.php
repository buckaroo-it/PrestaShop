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

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Form\Modifier;

use Buckaroo\PrestaShop\Src\Form\Entity\CustomProduct;
use Buckaroo\PrestaShop\Src\Form\Type\IdinTabType;
use PrestaShopBundle\Form\FormBuilderModifier;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductFormModifier
{
    /**
     * @var FormBuilderModifier
     */
    private $formBuilderModifier;

    /**
     * @param FormBuilderModifier $formBuilderModifier
     */
    public function __construct(
        FormBuilderModifier $formBuilderModifier
    ) {
        $this->formBuilderModifier = $formBuilderModifier;
    }

    /**
     * @param int|null $productId
     * @param FormBuilderInterface $productFormBuilder
     */
    public function modify(
        int $productId,
        FormBuilderInterface $productFormBuilder
    ): void {
        $idValue = $productId ? $productId : null;
        $customProduct = new CustomProduct($idValue);
        $this->formBuilderModifier->addAfter(
            $productFormBuilder, // the tab
            'options',
            'buckaroo_idin', // your field name
            IdinTabType::class,
            [
                'data' => [
                    'buckaroo_idin' => (bool) $customProduct->buckaroo_idin,
                ],
            ]
        );
    }
}
