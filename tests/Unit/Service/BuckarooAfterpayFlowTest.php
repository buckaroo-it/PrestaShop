<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooAfterpayFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testAfterpayFlowForB2bCompanyInNl(): void
    {
        \Country::$isoById = [
            1 => 'NL',
        ];

        $config = [
            'afterpay' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'customer_type' => \AfterPayCheckout::CUSTOMER_TYPE_B2B,
            ],
            'AFTERPAY' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
            ],
        ];

        $service = $this->createFlowServiceForMethod('afterpay', $config);

        // Company present in NL should make Afterpay available for B2B.
        $service->setMockAddress(10, 1, 'ACME BV');
        $service->setMockAddress(20, 1, 'ACME BV');

        $cart = new class {
            public $id_address_delivery = 20;
            public $id_address_invoice = 10;
            public $id_currency = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 100.0;
            }
        };

        $options = $service->getPaymentOptions($cart);

        $this->assertCount(1, $options, 'Afterpay should be available for NL B2B with company');
        $this->assertSame('afterpay', $options[0]->getModuleName());
    }

    public function testAfterpayFlowForB2bWithoutNlCompanyIsHidden(): void
    {
        \Country::$isoById = [
            1 => 'NL',
        ];

        $config = [
            'afterpay' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'customer_type' => \AfterPayCheckout::CUSTOMER_TYPE_B2B,
            ],
            'AFTERPAY' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
            ],
        ];

        $service = $this->createFlowServiceForMethod('afterpay', $config);

        // No company set should make Afterpay unavailable for B2B.
        $service->setMockAddress(10, 1, '');
        $service->setMockAddress(20, 1, '');

        $cart = new class {
            public $id_address_delivery = 20;
            public $id_address_invoice = 10;
            public $id_currency = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 100.0;
            }
        };

        $options = $service->getPaymentOptions($cart);

        $this->assertCount(0, $options, 'Afterpay should not be available for B2B without NL company');
    }
}

