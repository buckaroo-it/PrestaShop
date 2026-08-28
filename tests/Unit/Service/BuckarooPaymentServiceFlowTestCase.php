<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/BuckarooPaymentServiceTestDoubles.php';

/**
 * Base test case providing a factory for flow-oriented BuckarooPaymentService tests.
 */
abstract class BuckarooPaymentServiceFlowTestCase extends TestCase
{
    /**
     * Helper to build a flow-focused test double for a single payment method.
     *
     * This exercises the full getPaymentOptions() flow while still keeping
     * dependencies lightweight and in-memory.
     */
    protected function createFlowServiceForMethod(string $method, array $configByMethod): BuckarooPaymentServiceFlowTestable
    {
        $configService = new FakeBuckarooConfigService($configByMethod);

        $paymentMethodEntity = new class($method) {
            /** @var string */
            private $name;

            public function __construct(string $name)
            {
                $this->name = $name;
            }

            public function getId(): int
            {
                return 10;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getLabel(): string
            {
                return ucfirst($this->name);
            }

            public function getTemplate(): ?string
            {
                return null;
            }

            public function getIcon(): string
            {
                return $this->name . '.svg';
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

        $orderingRepository = new class($method) {
            private $method;

            public function __construct(string $method)
            {
                $this->method = $method;
            }

            public function fetchPositions($countryId, array $activeMethodIds): array
            {
                return [$this->method];
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
                return [
                    ['name' => 'buckarooKey', 'value' => $method],
                ];
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

                    public function assign($tplVar, $value = null)
                    {
                        // no-op for unit tests; supports assign(['key' => 'val']) and assign('key', 'val')
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

        return new BuckarooPaymentServiceFlowTestable(
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
    }
}

