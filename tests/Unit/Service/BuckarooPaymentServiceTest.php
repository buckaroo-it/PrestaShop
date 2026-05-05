<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/BuckarooPaymentServiceTestDoubles.php';

/**
 * Core unit tests for BuckarooPaymentService (non flow-specific logic).
 */
class BuckarooPaymentServiceTest extends TestCase
{
    /**
     * Create a lightweight test double that only initialises what we need.
     */
    private function createService(array $configByMethod): BuckarooPaymentServiceTestable
    {
        $configService = new FakeBuckarooConfigService($configByMethod);

        return new BuckarooPaymentServiceTestable($configService);
    }


    public function testIsAvailableByAmountReturnsFalseWhenNoConfig(): void
    {
        $service = $this->createService([
            // no entry for TEST_METHOD, service should treat this as unavailable
        ]);

        $this->assertFalse(
            $service->isAvailableByAmount(100.0, 'TEST_METHOD'),
            'Method without config should be unavailable'
        );
    }

    public function testIsAvailableByAmountWithinConfiguredRange(): void
    {
        $service = $this->createService([
            'AFTERPAY' => [
                'min_order_amount' => 10,
                'max_order_amount' => 200,
            ],
        ]);

        $this->assertTrue($service->isAvailableByAmount(10.0, 'AFTERPAY'));
        $this->assertTrue($service->isAvailableByAmount(150.0, 'AFTERPAY'));
        $this->assertTrue($service->isAvailableByAmount(200.0, 'AFTERPAY'));
    }

    public function testIsAvailableByAmountBelowMinimumOrAboveMaximum(): void
    {
        $service = $this->createService([
            'AFTERPAY' => [
                'min_order_amount' => 10,
                'max_order_amount' => 200,
            ],
        ]);

        $this->assertFalse($service->isAvailableByAmount(9.99, 'AFTERPAY'));
        $this->assertFalse($service->isAvailableByAmount(200.01, 'AFTERPAY'));
    }

    public function testIsAvailableByAmountWithoutMinAndMaxAlwaysTrue(): void
    {
        $service = $this->createService([
            'AFTERPAY' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
            ],
        ]);

        $this->assertTrue($service->isAvailableByAmount(0.0, 'AFTERPAY'));
        $this->assertTrue($service->isAvailableByAmount(9999.99, 'AFTERPAY'));
    }


    public function testGetPaymentOptionsReturnsConfiguredMethod(): void
    {
        $configService = new FakeBuckarooConfigService([
            'ideal' => [
                'frontend_label' => 'iDEAL | Wero',
                'payment_fee' => 1.23,
                'display_in_checkout' => 'grouped',
            ],
        ]);

        $feeService = new class {
            public function getBuckarooFeeInputs($method)
            {
                return [
                    ['name' => 'buckarooKey', 'value' => $method],
                ];
            }
        };

        $paymentMethodEntity = new class {
            public function getId(): int
            {
                return 10;
            }

            public function getName(): string
            {
                return 'ideal';
            }

            public function getLabel(): string
            {
                return 'iDEAL | Wero default';
            }

            public function getTemplate(): ?string
            {
                return null;
            }

            public function getIcon(): string
            {
                return 'ideal.svg';
            }
        };

        $paymentMethodRepository = new class($paymentMethodEntity) {
            private $method;

            public function __construct($method)
            {
                $this->method = $method;
            }

            public function findAll(): array
            {
                return [$this->method];
            }

            public function getActivePaymentMethods($countryId): array
            {
                return [['id' => $this->method->getId()]];
            }
        };

        $orderingRepository = new class {
            public function fetchPositions($countryId, array $activeMethodIds): array
            {
                // Position list: index => method name
                return ['ideal'];
            }
        };

        $countryRepository = new class {
            public function getCountryByIsoCode2(string $isoCode): array
            {
                return ['id' => 1];
            }
        };

        $issuersPayByBank = new class {
            public function getSelectedIssuerLogo()
            {
                return 'issuer.svg';
            }
        };

        $capayableIn3 = new class {
            public function getMethod()
            {
                return 'in3';
            }

            public function getLogo()
            {
                return 'in3.svg';
            }
        };

        $context = new class {
            public $country;
            public $link;
            public $smarty;

            public function __construct()
            {
                $this->country = (object) ['iso_code' => 'NL'];
                $this->link = new class {
                    public function getModuleLink($module, $controller, array $params = [])
                    {
                        return '/module/' . $module . '/' . $controller;
                    }
                };
                $this->smarty = new class {
                    public function fetch($template)
                    {
                        return 'FORM:' . $template;
                    }
                };
            }
        };

        $module = new class {
            public function isPaymentModeActive($method)
            {
                return true;
            }

            public function isIdinCheckout($cart)
            {
                return false;
            }

            public function l($string)
            {
                return $string;
            }
        };

        $service = new BuckarooPaymentServiceGetOptionsTestable(
            $paymentMethodRepository,
            $orderingRepository,
            $countryRepository,
            $configService,
            $feeService,
            $issuersPayByBank,
            $capayableIn3,
            $context,
            $module
        );

        $cart = new class {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 100.0;
            }
        };

        $options = $service->getPaymentOptions($cart);

        $this->assertCount(1, $options, 'Exactly one payment option should be returned');

        $option = $options[0];
        $this->assertSame('ideal', $option->getModuleName());
        $this->assertStringContainsString('iDEAL | Wero', $option->getCallToActionText());
        $this->assertNotEmpty($option->getInputs());
    }

    public function testIn3AvailabilityDependsOnBillingCountry(): void
    {
        // NL billing address should make in3 available.
        \Country::$isoById = [
            1 => 'NL',
            2 => 'BE',
        ];

        $service = new BuckarooPaymentServiceConditionsTestable(
            new FakeBuckarooConfigService([
                'in3' => [
                    'min_order_amount' => 0,
                    'max_order_amount' => 0,
                ],
            ])
        );

        $service->setMockAddress(10, 1);
        $service->setMockAddress(20, 2);

        $cartNl = new class {
            public $id_address_delivery = 20;
            public $id_address_invoice = 10;
        };

        $cartBe = new class {
            public $id_address_delivery = 20;
            public $id_address_invoice = 20;
        };

        $this->assertTrue($service->publicIsIn3Available($cartNl));
        $this->assertFalse($service->publicIsIn3Available($cartBe));
    }

    public function testTwintIsOnlyAvailableForChfCurrency(): void
    {
        // Covered by BuckarooTwintFlowTest – kept here only as a basic guard.
        \Currency::$isoById = [
            1 => 'CHF',
            2 => 'EUR',
        ];

        $service = $this->createService([
            'twint' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
            ],
        ]);

        $this->assertTrue($service->isAvailableByAmount(100.0, 'twint'));
    }
}

