<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooKlarnaFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testKlarnaSimpleFlowReturnsOption(): void
    {
        $config = [
            'klarna' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'frontend_label' => 'Klarna',
            ],
        ];

        $service = $this->createFlowServiceForMethod('klarna', $config);

        $cart = new class {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 55.0;
            }
        };

        $options = $service->getPaymentOptions($cart);

        $this->assertCount(1, $options);
        $this->assertSame('klarna', $options[0]->getModuleName());
    }
}

