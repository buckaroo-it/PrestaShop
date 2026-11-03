<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooWeroFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testWeroSimpleFlowReturnsOption(): void
    {
        $config = [
            'wero' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'frontend_label' => 'Wero',
            ],
        ];

        $service = $this->createFlowServiceForMethod('wero', $config);

        $cart = new class {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 20.0;
            }
        };

        $options = $service->getPaymentOptions($cart);

        $this->assertCount(1, $options);
        $this->assertSame('wero', $options[0]->getModuleName());
    }

    public function testWeroIsOnlyAvailableForEuro(): void
    {
        \Currency::$isoById = [1 => 'EUR', 2 => 'SEK'];

        $service = $this->createFlowServiceForMethod('wero', ['wero' => ['min_order_amount' => 0, 'max_order_amount' => 0]]);

        $cart = new class {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 2;

            public function getOrderTotal($withTaxes, $type)
            {
                return 20.0;
            }
        };

        $this->assertSame([], $service->getPaymentOptions($cart));
        $this->assertFalse($service->isMethodAvailableForCart($cart, 'wero'));

        $cart->id_currency = 1;
        $this->assertTrue($service->isMethodAvailableForCart($cart, 'wero'));
    }
}
