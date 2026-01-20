<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooAlipayFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testAlipaySimpleFlowReturnsOption(): void
    {
        $config = [
            'alipay' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'frontend_label' => 'Alipay',
            ],
        ];

        $service = $this->createFlowServiceForMethod('alipay', $config);

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
        $this->assertSame('alipay', $options[0]->getModuleName());
    }
}

