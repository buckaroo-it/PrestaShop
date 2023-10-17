<?php
declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Form\Modifier;

use Buckaroo\PrestaShop\Src\Form\Type\IdinTabType;
use PrestaShopBundle\Form\FormBuilderModifier;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Buckaroo\PrestaShop\Src\Form\Entity\CustomProduct;

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
