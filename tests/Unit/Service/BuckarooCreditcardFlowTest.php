<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooCreditcardFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testCreditcardGroupedFlowCreatesSingleOption(): void
    {
        $config = [
            'creditcard' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'display_in_checkout' => 'grouped',
                'frontend_label' => 'Credit Card',
            ],
        ];

        $service = $this->createFlowServiceForMethod('creditcard', $config);

        $cart = new class {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 75.0;
            }
        };

        $options = $service->getPaymentOptions($cart);

        $this->assertCount(1, $options, 'Grouped creditcard should result in one payment option');
        $this->assertSame('creditcard', $options[0]->getModuleName());
        $this->assertStringContainsString('Credit Card', $options[0]->getCallToActionText());
    }
}

