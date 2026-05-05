<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooPayByBankFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testPayByBankFlowUsesIssuerLogo(): void
    {
        $config = [
            'paybybank' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'frontend_label' => 'Pay by Bank',
            ],
        ];

        $service = $this->createFlowServiceForMethod('paybybank', $config);

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
        $this->assertCount(1, $options, 'Pay by Bank should be available with basic config');

        $option = $options[0];
        $this->assertSame('paybybank', $option->getModuleName());
        $this->assertStringContainsString('Pay by Bank', $option->getCallToActionText());
        $this->assertStringEndsWith('issuer.svg', $option->getLogo());
    }
}

