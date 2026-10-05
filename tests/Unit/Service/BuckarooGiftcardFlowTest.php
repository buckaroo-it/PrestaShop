<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooGiftcardFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testGiftcardGroupedFlowCreatesSingleOption(): void
    {
        $config = [
            'giftcard' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'display_in_checkout' => 'grouped',
                'frontend_label' => 'Giftcard',
            ],
        ];

        $service = $this->createFlowServiceForMethod('giftcard', $config);

        $cart = new class {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 30.0;
            }
        };

        $options = $service->getPaymentOptions($cart);

        $this->assertCount(1, $options, 'Grouped giftcard should result in one payment option');
        $this->assertSame('giftcard', $options[0]->getModuleName());
        $this->assertStringContainsString('Giftcard', $options[0]->getCallToActionText());
    }

    public function testGiftcardSeparateFlowCreatesOneOptionPerBrand(): void
    {
        $config = [
            'giftcard' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'display_in_checkout' => 'separate',
                'frontend_label' => 'Giftcard',
                'activeGiftcards' => [
                    'giftcards' => [
                        ['code' => 'boekenbon', 'name' => 'Boekenbon'],
                        ['code' => 'yourgift', 'name' => 'Your Gift'],
                    ],
                    'customGiftcards' => [
                        ['service_code' => 'customgiftcard', 'name' => 'Doenkado', 'logo' => 'https://example.com/logo.svg'],
                    ],
                ],
            ],
        ];

        $service = $this->createFlowServiceForMethod('giftcard', $config);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getIndividualGiftCards');
        $method->setAccessible(true);

        $details = new class {
            public function getLabel(): string
            {
                return 'Giftcards';
            }

            public function getIcon(): string
            {
                return 'Giftcards.svg';
            }

            public function getTemplate(): string
            {
                return 'payment_giftcards.tpl';
            }
        };

        $options = $method->invoke($service, 'giftcard', $details);

        $this->assertCount(3, $options, 'Inline giftcards should create one option per selected brand');

        $actions = array_map(static function ($option) {
            return $option->getAction();
        }, $options);

        foreach ($actions as $action) {
            $this->assertStringContainsString('applygiftcard', $action);
        }

        $inputCodes = [];
        foreach ($options as $option) {
            foreach ($option->getInputs() as $input) {
                if (($input['name'] ?? '') === 'cardCode') {
                    $inputCodes[] = $input['value'];
                }
            }
        }

        $this->assertContains('boekenbon', $inputCodes);
        $this->assertContains('yourgift', $inputCodes);
        $this->assertContains('customgiftcard', $inputCodes);
    }
}
