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
 *  @author    Buckaroo.nl <plugins@buckaroo.nl>
 *  @copyright Copyright (c) Buckaroo B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace BuckarooTest\Unit\Data;

use Buckaroo\PrestaShop\Src\Form\Modifier\ProductFormModifier;
use Buckaroo\PrestaShop\Src\Form\Type\IdinTabType;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use PrestaShopBundle\Form\FormBuilderModifier;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The iDIN tab added to the product page form.
 */
class FormTest extends TestCase
{
    private function formBuilder(): FormBuilderInterface
    {
        return new class implements FormBuilderInterface {
            /** @var array<string, array{0: mixed, 1: array}> */
            public $fields = [];

            public function add($child, $type = null, array $options = [])
            {
                $this->fields[$child] = [$type, $options];

                return $this;
            }
        };
    }

    public function testIdinTabTypeAddsTheDescriptionAndTheCheckbox(): void
    {
        $builder = $this->formBuilder();
        $currency = new \Currency();

        $type = new IdinTabType(null, ['en'], $currency);
        $type->buildForm($builder, []);

        self::assertSame(['idin_description', 'buckaroo_idin'], array_keys($builder->fields));

        [$descriptionType, $description] = $builder->fields['idin_description'];
        self::assertSame(TextType::class, $descriptionType);
        self::assertStringStartsWith('To require iDIN for specific products', $description['label']);
        self::assertFalse($description['required']);
        self::assertTrue($description['disabled']);
        self::assertSame(['style' => 'display:none;'], $description['attr']);

        [$checkboxType, $checkbox] = $builder->fields['buckaroo_idin'];
        self::assertSame(CheckboxType::class, $checkboxType);
        self::assertSame(['label' => 'Require iDIN verification for this product.', 'required' => false], $checkbox);

        self::assertSame($currency, self::getProperty($type, 'defaultCurrency'));
    }

    public function testIdinTabTypeSetsTheTabLabel(): void
    {
        $resolver = new OptionsResolver();

        (new IdinTabType(null, [], new \Currency()))->configureOptions($resolver);

        self::assertSame(['label' => 'iDIN Settings'], $resolver->resolve([]));
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public function productIdinProvider(): array
    {
        return [
            'required' => ['1', true],
            'not required' => ['0', false],
            'no row' => [false, false],
        ];
    }

    /**
     * @dataProvider productIdinProvider
     */
    public function testProductFormModifierAddsTheIdinTabAfterOptions($stored, bool $expected): void
    {
        Stubs::db('/FROM `ps_bk_product_idin`/', $stored);
        $modifier = new FormBuilderModifier();
        $builder = $this->formBuilder();

        (new ProductFormModifier($modifier))->modify(42, $builder);

        self::assertSame(['SELECT buckaroo_idin FROM `ps_bk_product_idin` WHERE (product_id = 42)'], Stubs::sql('getValue'));
        self::assertSame(
            [[$builder, 'options', 'buckaroo_idin', IdinTabType::class, ['data' => ['buckaroo_idin' => $expected]]]],
            Stubs::callsTo(FormBuilderModifier::class . '->addAfter')
        );
    }
}
