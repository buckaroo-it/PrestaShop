<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooClickToPayFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testClickToPayFlowReturnsOption(): void
    {
        $config = [
            'clicktopay' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'frontend_label' => 'Click to Pay',
            ],
        ];

        $service = $this->createFlowServiceForMethod('clicktopay', $config);

        $options = $service->getPaymentOptions($this->createCart(20.0));

        $this->assertCount(1, $options);
        $this->assertSame('clicktopay', $options[0]->getModuleName());
        $this->assertSame('Click to Pay', $options[0]->getCallToActionText());
    }

    public function testClickToPayIsHiddenOutsideConfiguredOrderAmounts(): void
    {
        $config = [
            'clicktopay' => [
                'min_order_amount' => 25,
                'max_order_amount' => 100,
                'frontend_label' => 'Click to Pay',
            ],
        ];

        $service = $this->createFlowServiceForMethod('clicktopay', $config);

        $this->assertCount(0, $service->getPaymentOptions($this->createCart(20.0)));
        $this->assertCount(1, $service->getPaymentOptions($this->createCart(50.0)));
    }

    private function createCart(float $total)
    {
        return new class($total) {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 1;

            /** @var float */
            private $total;

            public function __construct(float $total)
            {
                $this->total = $total;
            }

            public function getOrderTotal($withTaxes, $type)
            {
                return $this->total;
            }
        };
    }
}
