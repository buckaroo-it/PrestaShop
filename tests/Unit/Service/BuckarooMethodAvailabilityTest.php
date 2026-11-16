<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/BuckarooPaymentServiceTestDoubles.php';

/**
 * Server-side availability rules used by the request and applygiftcard
 * controllers before an order or payment is created (BTI-1717).
 */
class BuckarooMethodAvailabilityTest extends TestCase
{
    /** @var object */
    private $module;

    protected function setUp(): void
    {
        $this->module = new class {
            /** @var string[] */
            public $inactive = [];
            /** @var bool */
            public $idinCheckout = false;

            public function isPaymentModeActive($method)
            {
                return !in_array($method, $this->inactive, true);
            }

            public function isIdinCheckout($cart)
            {
                return $this->idinCheckout;
            }

            public function l($string)
            {
                return $string;
            }
        };
    }

    protected function tearDown(): void
    {
        \Country::$isoById = [];
        \Currency::$isoById = [];
    }

    public function testMethodOutsideMinMaxIsRejected(): void
    {
        $service = $this->createService(['ideal'], ['ideal' => ['min_order_amount' => 10, 'max_order_amount' => 100]]);

        $this->assertTrue($service->isMethodAvailableForCart($this->cart(50.0), 'ideal'));
        $this->assertFalse($service->isMethodAvailableForCart($this->cart(5.0), 'ideal'));
        $this->assertFalse($service->isMethodAvailableForCart($this->cart(150.0), 'ideal'));
    }

    public function testMethodNotEnabledForCountryIsRejected(): void
    {
        $service = $this->createService(['ideal'], ['ideal' => [], 'paypal' => []]);

        $this->assertFalse($service->isMethodAvailableForCart($this->cart(50.0), 'paypal'));
    }

    public function testInactiveMethodIsRejected(): void
    {
        $this->module->inactive = ['giftcard'];
        $service = $this->createService(['giftcard'], ['giftcard' => []]);

        $this->assertFalse($service->isMethodAvailableForCart($this->cart(50.0), 'giftcard'));
    }

    public function testUnknownMethodIsRejected(): void
    {
        $service = $this->createService(['ideal'], ['ideal' => []]);

        $this->assertFalse($service->isMethodAvailableForCart($this->cart(50.0), 'nonexistent'));
    }

    public function testIn3RequiresDutchBillingAddress(): void
    {
        \Country::$isoById = [1 => 'NL', 2 => 'BE'];
        $service = $this->createService(['in3'], ['in3' => []]);
        $service->setMockAddress(10, 1);
        $service->setMockAddress(20, 2);

        $this->assertTrue($service->isMethodAvailableForCart($this->cart(50.0, 10), 'in3'));
        $this->assertFalse($service->isMethodAvailableForCart($this->cart(50.0, 20), 'in3'));
    }

    public function testOnlyIdinIsAvailableWhileAgeVerificationIsPending(): void
    {
        $this->module->idinCheckout = true;
        $service = $this->createService(['ideal', 'idin'], ['ideal' => [], 'idin' => []]);
        $service->setIdinValid(false);
        $cart = $this->cart(50.0);

        $this->assertFalse($service->isMethodAvailableForCart($cart, 'ideal'));
        $this->assertTrue($service->isMethodAvailableForCart($cart, 'idin'));

        $options = $service->getPaymentOptions($cart);
        $this->assertCount(1, $options);
        $this->assertSame('idin', $options[0]->getModuleName());
    }

    public function testVerifiedCustomerCanPayAndIsNotAskedForIdin(): void
    {
        $this->module->idinCheckout = true;
        $service = $this->createService(['ideal', 'idin'], ['ideal' => [], 'idin' => []]);
        $service->setIdinValid(true);
        $cart = $this->cart(50.0);

        $this->assertTrue($service->isMethodAvailableForCart($cart, 'ideal'));
        $this->assertFalse($service->isMethodAvailableForCart($cart, 'idin'));
    }

    public function testIdinIsNotAvailableWhenNoProductRequiresIt(): void
    {
        $service = $this->createService(['ideal', 'idin'], ['ideal' => [], 'idin' => []]);

        $this->assertTrue($service->isMethodAvailableForCart($this->cart(50.0), 'ideal'));
        $this->assertFalse($service->isMethodAvailableForCart($this->cart(50.0), 'idin'));
    }

    public function testCardBrandMustBeAnActiveCreditCard(): void
    {
        $service = $this->createService(['creditcard'], ['creditcard' => []], [
            'visa' => ['name' => 'Visa', 'logo' => 'visa.svg'],
            'mastercard' => ['name' => 'Mastercard', 'logo' => 'mc.svg'],
        ]);

        $this->assertTrue($service->isCreditCardBrandAllowed('visa'));
        $this->assertTrue($service->isCreditCardBrandAllowed('MasterCard'));
        $this->assertFalse($service->isCreditCardBrandAllowed('amex'));
        $this->assertFalse($service->isCreditCardBrandAllowed('ideal'));
        $this->assertFalse($service->isCreditCardBrandAllowed(''));
    }

    public function testGiftcardCodeMustBeAllowedByTheMerchant(): void
    {
        $service = $this->createService(['giftcard'], ['giftcard' => []], [], ['boekenbon', 'customgiftcard_Shop']);

        $this->assertSame('boekenbon', $service->resolveAllowedGiftcardCode('boekenbon'));
        $this->assertSame('customgiftcard_Shop', $service->resolveAllowedGiftcardCode('CUSTOMGIFTCARD_shop'));
        $this->assertSame('', $service->resolveAllowedGiftcardCode('fashioncheque'));
        $this->assertSame('', $service->resolveAllowedGiftcardCode('ideal'));
        $this->assertSame('', $service->resolveAllowedGiftcardCode(''));
    }

    public function testPayByBankIssuerMustBeOffered(): void
    {
        $service = $this->createService(['paybybank'], ['paybybank' => []]);

        $this->assertTrue($service->isPayByBankIssuerAllowed('INGBNL2A'));
        $this->assertFalse($service->isPayByBankIssuerAllowed('FAKEBANK'));
        $this->assertFalse($service->isPayByBankIssuerAllowed('0'));
        $this->assertFalse($service->isPayByBankIssuerAllowed(''));
    }

    private function cart(float $total, int $addressId = 1)
    {
        return new class($total, $addressId) {
            public $id_customer = 1;
            public $id_address_delivery;
            public $id_address_invoice;
            public $id_currency = 1;
            private $total;

            public function __construct(float $total, int $addressId)
            {
                $this->total = $total;
                $this->id_address_delivery = $addressId;
                $this->id_address_invoice = $addressId;
            }

            public function getOrderTotal($withTaxes, $type)
            {
                return $this->total;
            }
        };
    }

    /**
     * @param string[] $countryMethods methods enabled for the shopper's country
     * @param array<string, array<string, mixed>> $configByMethod
     */
    private function createService(
        array $countryMethods,
        array $configByMethod,
        array $activeCreditCards = [],
        array $allowedGiftcardCodes = []
    ): BuckarooPaymentServiceFlowTestable {
        $entities = [];
        foreach (array_keys($configByMethod) as $name) {
            $entities[] = new class($name) {
                private $name;

                public function __construct(string $name)
                {
                    $this->name = $name;
                }

                public function getId(): int
                {
                    return crc32($this->name);
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
        }

        $paymentMethodRepository = new class($entities) {
            private $entities;

            public function __construct(array $entities)
            {
                $this->entities = $entities;
            }

            public function findAll(): array
            {
                return $this->entities;
            }

            public function getActivePaymentMethods($countryId): array
            {
                return [];
            }
        };

        $orderingRepository = new class($countryMethods) {
            private $methods;

            public function __construct(array $methods)
            {
                $this->methods = $methods;
            }

            public function fetchPositions($countryId, array $activeMethodIds): array
            {
                return $this->methods;
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
                return [];
            }
        };

        $issuersPayByBank = new class {
            public function get(): array
            {
                return [
                    'INGBNL2A' => ['name' => 'ING', 'logo' => 'ING.svg', 'selected' => false],
                    'RABONL2U' => ['name' => 'Rabobank', 'logo' => 'Rabobank.svg', 'selected' => false],
                ];
            }

            public function getSelectedIssuerLogo()
            {
                return 'PayByBank.svg';
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
                        return '';
                    }

                    public function assign($tplVar, $value = null)
                    {
                    }
                };
            }
        };

        return new BuckarooPaymentServiceFlowTestable(
            $paymentMethodRepository,
            $orderingRepository,
            $countryRepository,
            new FakeBuckarooConfigService($configByMethod, $activeCreditCards, $allowedGiftcardCodes),
            $feeService,
            $issuersPayByBank,
            $capayableIn3,
            $context,
            $this->module
        );
    }
}
