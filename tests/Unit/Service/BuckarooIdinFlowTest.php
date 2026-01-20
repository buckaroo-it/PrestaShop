<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

require_once __DIR__ . '/BuckarooPaymentServiceFlowTestCase.php';

class BuckarooIdinFlowTest extends BuckarooPaymentServiceFlowTestCase
{
    public function testIdinFlowOnlyShowsWhenCheckoutRequiresAndCustomerNotValidated(): void
    {
        $config = [
            'idin' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'frontend_label' => 'IDIN',
            ],
        ];

        $configService = new FakeBuckarooConfigService($config);

        $paymentMethodEntity = new class {
            public function getId(): int
            {
                return 10;
            }

            public function getName(): string
            {
                return 'idin';
            }

            public function getLabel(): string
            {
                return 'IDIN';
            }

            public function getTemplate(): ?string
            {
                return null;
            }

            public function getIcon(): string
            {
                return 'idin.svg';
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
                return ['idin'];
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
                return true;
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

        // Customer not yet validated for IDIN.
        $service->setIdinValid(false);

        $cart = new class {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 1;
            public $id_customer = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 20.0;
            }
        };

        $options = $service->getPaymentOptions($cart);

        $this->assertCount(1, $options, 'IDIN should be available when checkout requires it and customer is not validated');
        $this->assertSame('idin', $options[0]->getModuleName());
    }

    public function testIdinFlowHiddenWhenCustomerAlreadyValidated(): void
    {
        $config = [
            'idin' => [
                'min_order_amount' => 0,
                'max_order_amount' => 0,
                'frontend_label' => 'IDIN',
            ],
        ];

        $service = $this->createFlowServiceForMethod('idin', $config);

        // Simulate that IDIN is required in checkout but already validated.
        $service->setIdinValid(true);

        $cart = new class {
            public $id_address_delivery = 1;
            public $id_address_invoice = 1;
            public $id_currency = 1;
            public $id_customer = 1;

            public function getOrderTotal($withTaxes, $type)
            {
                return 20.0;
            }
        };

        // Override module behaviour for this scenario.
        $service->getContext()->link = new class {
            public function getModuleLink($module, $controller, array $params = [])
            {
                return '/module/' . $module . '/' . $controller;
            }
        };

        $options = $service->getPaymentOptions($cart);

        $this->assertCount(0, $options, 'IDIN should be hidden when customer is already validated');
    }
}

