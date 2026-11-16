<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooGooglepayFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testGooglepayIsListedByDefault(): void
    {
        $service = $this->createFlowServiceForMethod('googlepay', [
            'googlepay' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'frontend_label' => 'Google Pay',
            ],
        ]);

        $options = $service->getPaymentOptions($this->cart());

        $this->assertCount(1, $options);
        $this->assertSame('googlepay', $options[0]->getModuleName());
    }

    public function testGooglepayOfferedOnlyAsExpressButtonIsNotAPaymentOption(): void
    {
        $service = $this->createFlowServiceForMethod('googlepay', [
            'googlepay' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'frontend_label' => 'Google Pay',
                'checkout_method' => '0',
            ],
        ]);
        $cart = $this->cart();

        $this->assertSame([], $service->getPaymentOptions($cart));
        // The express button still passes the method's availability rules.
        $this->assertTrue($service->isMethodAvailableForCart($cart, 'googlepay'));
    }

    private function cart(): object
    {
        return new class {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 30.0;
            }
        };
    }
}
