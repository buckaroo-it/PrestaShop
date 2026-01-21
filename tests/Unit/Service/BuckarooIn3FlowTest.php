<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooIn3FlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testIn3FlowOnlyShowsForNlBillingCountry(): void
    {
        \Country::$isoById = [
            1 => 'NL',
            2 => 'BE',
        ];

        $configService = new FakeBuckarooConfigService([
            'in3' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
            ],
        ]);

        $paymentMethodEntity = new class {
            public function getId(): int
            {
                return 10;
            }

            public function getName(): string
            {
                return 'in3';
            }

            public function getLabel(): string
            {
                return 'in3';
            }

            public function getTemplate(): ?string
            {
                return null;
            }

            public function getIcon(): string
            {
                return 'in3.svg';
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
                return ['in3'];
            }
        };

        $countryRepository = new class {
            public function getCountryByIsoCode2(string $isoCode): array
            {
                return ['id' => 1];
            }
        };

        $feeService = new class {
            public function getBuckarooFeeInputs($method)
            {
                return [['name' => 'buckarooKey', 'value' => $method]];
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

        $service = new BuckarooPaymentServiceFlowTestable(
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

        $service->setMockAddress(10, 1);
        $service->setMockAddress(20, 2);

        $cartNl = new class {
            public $id_address_delivery = 20;
            public $id_address_invoice = 10;
            public $id_currency = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 100.0;
            }
        };

        $cartBe = new class {
            public $id_address_delivery = 20;
            public $id_address_invoice = 20;
            public $id_currency = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 100.0;
            }
        };

        $optionsNl = $service->getPaymentOptions($cartNl);
        $this->assertCount(1, $optionsNl, 'in3 should be available for NL billing country');
        $this->assertSame('in3', $optionsNl[0]->getModuleName());

        $optionsBe = $service->getPaymentOptions($cartBe);
        $this->assertCount(0, $optionsBe, 'in3 should not be available for non-NL billing country');
    }
}

