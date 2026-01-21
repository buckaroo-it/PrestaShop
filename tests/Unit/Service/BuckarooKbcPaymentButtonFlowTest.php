<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooKbcPaymentButtonFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testKbcPaymentButtonSimpleFlowReturnsOption(): void
    {
        $config = [
            'kbcpaymentbutton' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'frontend_label' => 'KBC Payment Button',
            ],
        ];

        $service = $this->createFlowServiceForMethod('kbcpaymentbutton', $config);

        $cart = new class {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 60.0;
            }
        };

        $options = $service->getPaymentOptions($cart);

        $this->assertCount(1, $options);
        $this->assertSame('kbcpaymentbutton', $options[0]->getModuleName());
    }
}

