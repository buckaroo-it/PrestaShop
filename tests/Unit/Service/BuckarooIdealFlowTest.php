<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooIdealFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testIdealSimpleFlowReturnsOption(): void
    {
        $config = [
            'ideal' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'frontend_label' => 'iDEAL',
            ],
        ];

        $service = $this->createFlowServiceForMethod('ideal', $config);

        $cart = new class {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 50.0;
            }
        };

        $options = $service->getPaymentOptions($cart);

        $this->assertCount(1, $options);
        $this->assertSame('ideal', $options[0]->getModuleName());
    }
}

