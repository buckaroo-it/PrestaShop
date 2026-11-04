<?php
/**
 * Shared setup for the checkout tests: a cart with an NL invoice address, a
 * buckaroo3 module double and doubles that stand in for the payment request,
 * so startPayment() never builds a BuckarooClient.
 */

namespace BuckarooTest\Unit\Checkout;

use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/StubsSupport.php';

abstract class CheckoutTestCase extends TestCase
{
    public const CART_ID = 5;
    public const CUSTOMER_ID = 3;
    public const INVOICE_ADDRESS_ID = 10;
    public const DELIVERY_ADDRESS_ID = 20;
    public const COUNTRY_NL = 1;
    public const COUNTRY_DE = 2;

    /** @var \Module */
    protected $module;

    /** @var BuckarooConfigService|\PHPUnit\Framework\MockObject\MockObject */
    protected $configService;

    /** @var array<string, array<string, mixed>> method => key => value read through getConfigValue() */
    protected $configValues = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Loaded inside a test so its file-level lines are part of the coverage run.
        require_once _PS_MODULE_DIR_ . 'buckaroo3/library/checkout/checkout.php';
        foreach (glob(_PS_MODULE_DIR_ . 'buckaroo3/library/checkout/*checkout.php') as $file) {
            require_once _PS_MODULE_DIR_ . 'buckaroo3/library/checkout/' . basename($file);
        }

        \Country::$isoById = [self::COUNTRY_NL => 'NL', self::COUNTRY_DE => 'DE'];

        $this->configValues = [];
        $this->configService = $this->createMock(BuckarooConfigService::class);
        $values = &$this->configValues;
        $this->configService->method('getConfigValue')->willReturnCallback(
            function ($method, $key) use (&$values) {
                return $values[$method][$key] ?? null;
            }
        );

        $this->module = new \Module();
        $this->module->stub('getBuckarooConfigService', $this->configService);
        $this->module->stub('getBuckarooFeeService', null);
        $this->module->stub('getBuckarooFee', null);
        \Module::$instances['buckaroo3'] = $this->module;

        Stubs::object('Customer', self::CUSTOMER_ID, ['email' => 'jan@example.com']);
        $this->registerAddress(self::INVOICE_ADDRESS_ID);
        $this->registerAddress(self::DELIVERY_ADDRESS_ID, [
            'firstname' => 'Piet',
            'lastname' => 'Pietersen',
            'address1' => 'Kerkstraat 22 b',
            'postcode' => '5678CD',
            'city' => 'Utrecht',
        ]);

        // Product images (afterpay/klarna articles): no cover and no image types unless a test sets them.
        Stubs::returns('Product::getCover', false);
        Stubs::returns('ImageType::getImagesTypes', []);

        // Gateway mode lookup done by Checkout::getInstance() through Config::getMode().
        Stubs::db('/bk_payment_methods/', 7);
        Stubs::db('/bk_configuration/', json_encode(['mode' => 'live']));
    }

    protected function registerAddress(int $id, array $props = []): void
    {
        Stubs::object('Address', $id, array_merge([
            'id_customer' => self::CUSTOMER_ID,
            'id_country' => self::COUNTRY_NL,
            'firstname' => 'Jan',
            'lastname' => 'Jansen',
            'company' => '',
            'address1' => 'Hoofdstraat 12 A',
            'address2' => '',
            'postcode' => '1234AB',
            'city' => 'Amsterdam',
            'phone' => '020-1234567',
            'phone_mobile' => '06-12345678',
            'vat_number' => 'NL123',
            'id_state' => 0,
        ], $props));
    }

    /**
     * @param array<int|string, float> $totals Cart::* type => amount ('3_excl' for tax excluded)
     */
    protected function makeCart(array $totals = [], array $products = [], bool $separateDelivery = true): \Cart
    {
        $cart = new \Cart();
        $cart->id = self::CART_ID;
        $cart->id_customer = self::CUSTOMER_ID;
        $cart->id_address_invoice = self::INVOICE_ADDRESS_ID;
        $cart->id_address_delivery = $separateDelivery ? self::DELIVERY_ADDRESS_ID : self::INVOICE_ADDRESS_ID;
        $cart->id_currency = 1;
        $cart->totals = $totals + [\Cart::BOTH => 100.0];
        $cart->products = $products;

        return $cart;
    }

    /** Build a checkout through the real factory method, the way request.php does. */
    protected function makeCheckout(string $method, ?\Cart $cart = null): \Checkout
    {
        $checkout = \Checkout::getInstance($method, $cart ?: $this->makeCart(), $this->context());
        $checkout->setReference('REF');
        $checkout->platformName = 'PrestaShop';
        $checkout->platformVersion = '9.0.1.0';
        $checkout->moduleSupplier = 'Buckaroo';
        $checkout->moduleName = 'buckaroo3';
        $checkout->moduleVersion = '5.0.0';
        $checkout->returnUrl = 'https://shop.test/return';
        $checkout->pushUrl = 'https://shop.test/push';

        return $checkout;
    }

    /** @return mixed the PaymentMethod the checkout configures */
    protected function paymentRequest(\Checkout $checkout)
    {
        return self::getProperty($checkout, 'payment_request');
    }

    protected function customVars(\Checkout $checkout): array
    {
        return self::getProperty($checkout, 'customVars');
    }

    /** Replace the real PaymentMethod with a recording double (keeps its public fields). */
    protected function swapRequest(\Checkout $checkout, ?FakeResponse $response = null): FakePaymentRequest
    {
        $double = new FakePaymentRequest($response ?: new FakeResponse());
        $real = $this->paymentRequest($checkout);
        if (is_object($real)) {
            foreach (get_object_vars($real) as $name => $value) {
                $double->{$name} = $value;
            }
        }
        self::setProperty($checkout, 'payment_request', $double);

        return $double;
    }

    /** Tax manager double answering TaxManagerFactory::getManager() with a fixed rate. */
    protected function taxRate(float $rate): void
    {
        $calculator = new class($rate) {
            private $rate;

            public function __construct($rate)
            {
                $this->rate = $rate;
            }

            public function getTotalRate()
            {
                return $this->rate;
            }
        };
        $manager = new class($calculator) {
            private $calculator;

            public function __construct($calculator)
            {
                $this->calculator = $calculator;
            }

            public function getTaxCalculator()
            {
                return $this->calculator;
            }
        };
        Stubs::returns('TaxManagerFactory::getManager', $manager);
    }

    /** Make CarrierHandler::handleSendCloud() return a service point. */
    protected function sendCloudServicePoint(array $details): void
    {
        Stubs::object('Carrier', 9, ['external_module_name' => 'sendcloud']);
        $point = new class((object) $details) {
            private $details;

            public function __construct($details)
            {
                $this->details = $details;
            }

            public function getDetails()
            {
                return $this->details;
            }
        };
        Stubs::returns('SendcloudServicePoint::getFromCart', $point);
    }
}

/** Response double: the subset of BuckarooResponse the checkouts read. */
class FakeResponse
{
    public $statuscode;
    private $succeeded;
    private $redirect;
    private $redirectUrl;

    public function __construct(bool $succeeded = true, bool $redirect = true, string $redirectUrl = 'https://checkout.buckaroo.test/pay', $statuscode = 790)
    {
        $this->succeeded = $succeeded;
        $this->redirect = $redirect;
        $this->redirectUrl = $redirectUrl;
        $this->statuscode = $statuscode;
    }

    public function hasSucceeded()
    {
        return $this->succeeded;
    }

    public function isRedirectRequired()
    {
        return $this->redirect;
    }

    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }
}

/** Payment request double: records every call and returns the configured response. */
#[\AllowDynamicProperties]
class FakePaymentRequest
{
    /** @var array<int, array{0: string, 1: array}> */
    public $calls = [];

    private $response;

    public function __construct(FakeResponse $response)
    {
        $this->response = $response;
    }

    public function __call($method, $args)
    {
        $this->calls[] = [$method, $args];

        return $this->response;
    }
}
