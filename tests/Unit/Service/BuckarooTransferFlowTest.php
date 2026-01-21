<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooTransferFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testTransferSimpleFlowReturnsOption(): void
    {
        $config = [
            'transfer' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'frontend_label' => 'Bank Transfer',
            ],
        ];

        $service = $this->createFlowServiceForMethod('transfer', $config);

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
        $this->assertSame('transfer', $options[0]->getModuleName());
    }
}

