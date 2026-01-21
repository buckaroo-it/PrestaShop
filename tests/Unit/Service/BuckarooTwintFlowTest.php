<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooTwintFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testTwintIsOnlyAvailableForChfCurrency(): void
    {
        // Configure currencies: id 1 = CHF, id 2 = EUR.
        \Currency::$isoById = [
            1 => 'CHF',
            2 => 'EUR',
        ];

        $configService = new FakeBuckarooConfigService([
            'twint' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
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
                return 'twint';
            }

            public function getLabel(): string
            {
                return 'TWINT';
            }

            public function getTemplate(): ?string
            {
                return null;
            }

            public function getIcon(): string
            {
                return 'twint.svg';
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
                return ['twint'];
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
                $this->country = (object) ['iso_code' => 'CH'];
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

        $cartChf = new class {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 100.0;
            }
        };

        $cartEur = new class {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 2;

            public function getOrderTotal($withTaxes, $type)
            {
                return 100.0;
            }
        };

        $optionsChf = $service->getPaymentOptions($cartChf);
        $this->assertCount(1, $optionsChf, 'TWINT should be available for CHF carts');

        $optionsEur = $service->getPaymentOptions($cartEur);
        $this->assertCount(0, $optionsEur, 'TWINT should not be available for non-CHF carts');
    }
}

