<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooSwishFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testSwishIsOnlyAvailableForSekCurrency(): void
    {
        \Currency::$isoById = [
            1 => 'SEK',
            2 => 'EUR',
        ];

        $configService = new FakeBuckarooConfigService([
            'swish' => [
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
                return 'swish';
            }

            public function getLabel(): string
            {
                return 'Swish';
            }

            public function getTemplate(): ?string
            {
                return null;
            }

            public function getIcon(): string
            {
                return 'swish.svg';
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
                return ['swish'];
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
                $this->country = (object) ['iso_code' => 'SE'];
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

        $cartSek = new class {
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

        $optionsSek = $service->getPaymentOptions($cartSek);
        $this->assertCount(1, $optionsSek, 'Swish should be available for SEK carts');

        $optionsEur = $service->getPaymentOptions($cartEur);
        $this->assertCount(0, $optionsEur, 'Swish should not be available for non-SEK carts');
    }
}

