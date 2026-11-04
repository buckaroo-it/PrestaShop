<?php

namespace BuckarooTest\Unit\ExpressAndGiftcard;

use Buckaroo\PrestaShop\Src\ApplePay\ExpressCheckoutService;
use Buckaroo\PrestaShop\Src\ApplePay\ExpressException;
use Buckaroo\PrestaShop\Src\ApplePay\ExpressSettings;
use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentService;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/ExpressTestSupport.php';

/**
 * JSON endpoints of the Apple Pay express button (start, contact, shipping, authorize).
 *
 * Separate processes: PHPUnit has already written output in its own process,
 * so header() and http_response_code() could not set the status asserted here.
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class ApplePayExpressControllerTest extends TestCase
{
    use RunsJsonEndpoints;

    private const GENERIC_ERROR = 'Apple Pay is not available at the moment. Please try again or choose another payment method.';

    /** @var ExpressTestModule */
    private $module;

    /** @var ExpressCheckoutService&\PHPUnit\Framework\MockObject\MockObject */
    private $service;

    /** @var \Cart */
    private $expressCart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = new ExpressTestModule();
        $this->module->paymentModes['applepay'] = true;
        $this->module->services['buckaroo.config.api.payment.service'] = $this->createMock(BuckarooPaymentService::class);
        \Module::$instances['buckaroo3'] = $this->module;

        $this->expressCart = new \Cart();
        $this->expressCart->id = 500;
        $this->service = $this->createMock(ExpressCheckoutService::class);
        $this->service->method('getExpressCart')->willReturn($this->expressCart);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        http_response_code(200);
        parent::tearDown();
    }

    private function controller(bool $withServiceDouble = true): \Buckaroo3ApplepayexpressModuleFrontController
    {
        $controller = new \Buckaroo3ApplepayexpressModuleFrontController();
        if ($withServiceDouble) {
            self::setProperty($controller, 'expressService', $this->service);
        }

        return $controller;
    }

    /** POST the action with a valid token and return the single JSON response. */
    private function call(array $post, ?\Buckaroo3ApplepayexpressModuleFrontController $controller = null): array
    {
        $controller = $controller ?: $this->controller();
        $this->request([], $post + ['token' => 'token']);

        self::withoutHeaderWarnings(function () use ($controller) {
            $controller->initContent();
        });

        $output = self::jsonOutput($controller);
        self::assertCount(1, $output, 'exactly one JSON response');

        return $output[0];
    }

    private static function summary(): array
    {
        return [
            'lineItems' => [['label' => '1 x Mug', 'amount' => '9.50', 'type' => 'final']],
            'total' => ['label' => 'Shop', 'amount' => '14.45', 'type' => 'final'],
            'shippingMethods' => [['identifier' => '5,', 'label' => 'PostNL', 'detail' => '', 'amount' => '4.95']],
            'selectedShipping' => '5,',
        ];
    }

    public function testInitAlwaysAnswersJson(): void
    {
        $controller = $this->controller();
        $controller->ajax = false;

        $controller->init();

        self::assertTrue($controller->ajax);
        self::assertTrue($controller->content_only);
    }

    public function testRequestsOtherThanPostAreRejected(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $response = $this->call(['action' => 'start']);

        self::assertSame(['status' => 'error', 'message' => 'Unauthorized request', 'field' => ''], $response);
        self::assertSame(403, http_response_code());
    }

    public function testRequestsWithoutTheShopTokenAreRejected(): void
    {
        $response = $this->call(['action' => 'start', 'token' => 'forged']);

        self::assertSame('Unauthorized request', $response['message']);
        self::assertSame(403, http_response_code());
    }

    public function unavailableModule(): array
    {
        return [
            'module disabled' => [false, true, 'GUID'],
            'Apple Pay disabled' => [true, false, 'GUID'],
            'no merchant GUID' => [true, true, ''],
        ];
    }

    /**
     * @dataProvider unavailableModule
     */
    public function testExpressPaymentsNeedAnActiveAndConfiguredApplePay(bool $active, bool $applePay, string $guid): void
    {
        $this->module->active = $active;
        $this->module->paymentModes['applepay'] = $applePay;
        $this->module->settings = new ExpressSettings(['merchant_guid' => $guid]);
        $this->service->expects(self::never())->method('startFromCart');

        $response = $this->call(['action' => 'start', 'source' => 'cart']);

        self::assertSame(['status' => 'error', 'message' => self::GENERIC_ERROR, 'field' => ''], $response);
        self::assertSame(200, http_response_code());
    }

    public function testStartFromTheProductPageReturnsTheFirstEstimate(): void
    {
        $cart = new \Cart();
        $this->service->expects(self::once())->method('startFromProduct')
            ->with(10, 33, ['1' => '5'], 2)
            ->willReturn($cart);
        $this->service->expects(self::once())->method('isAvailable')->with($cart)->willReturn(true);
        $this->service->expects(self::once())->method('estimate')
            ->with($cart, ['countryCode' => 'NL'], null)
            ->willReturn(self::summary());

        $response = $this->call([
            'action' => 'start',
            'source' => 'product',
            'id_product' => '10',
            'id_product_attribute' => '33',
            'group' => ['1' => '5'],
            'qty' => '2',
            'contact' => json_encode(['countryCode' => 'NL']),
        ]);

        self::assertSame(['status' => 'success'] + self::summary(), $response);
    }

    public function testStartFromTheProductPageDefaultsToOneUnitWithoutAttributes(): void
    {
        $this->service->expects(self::once())->method('startFromProduct')
            ->with(10, 0, [], 1)
            ->willReturn(new \Cart());
        $this->service->method('isAvailable')->willReturn(true);
        $this->service->expects(self::once())->method('estimate')
            ->with(self::anything(), [], null)
            ->willReturn(self::summary());

        $response = $this->call(['action' => 'start', 'source' => 'product', 'id_product' => '10', 'group' => 'x', 'contact' => 'not json']);

        self::assertSame('success', $response['status']);
    }

    public function testStartFromTheCartPage(): void
    {
        $cart = new \Cart();
        $this->service->expects(self::once())->method('startFromCart')->willReturn($cart);
        $this->service->expects(self::never())->method('startFromProduct');
        $this->service->method('isAvailable')->willReturn(true);
        $this->service->method('estimate')->willReturn(self::summary());

        $response = $this->call(['action' => 'start', 'source' => 'checkout']);

        self::assertSame('5,', $response['selectedShipping']);
    }

    public function hiddenPlacements(): array
    {
        return [
            'unknown page' => ['home', null],
            'page not enabled' => ['product', ['cart']],
        ];
    }

    /**
     * @dataProvider hiddenPlacements
     */
    public function testStartIsRefusedWhereTheButtonIsNotShown(string $source, $showOn): void
    {
        $config = ['merchant_guid' => 'GUID'];
        if ($showOn !== null) {
            $config['express_show_on'] = $showOn;
        }
        $this->module->settings = new ExpressSettings($config);
        $this->service->expects(self::never())->method('startFromProduct');

        $response = $this->call(['action' => 'start', 'source' => $source]);

        self::assertSame(self::GENERIC_ERROR, $response['message']);
    }

    public function testStartIsRefusedWhenApplePayIsNotAllowedForTheCart(): void
    {
        $this->service->method('startFromCart')->willReturn(new \Cart());
        $this->service->method('isAvailable')->willReturn(false);
        $this->service->expects(self::never())->method('estimate');

        $response = $this->call(['action' => 'start', 'source' => 'cart']);

        self::assertSame(['status' => 'error', 'message' => 'Apple Pay is not available for this order. Please choose another payment method.', 'field' => ''], $response);
    }

    public function testStartReportsAnUndeliverableAddressInTheSheet(): void
    {
        $this->service->method('startFromCart')->willReturn(new \Cart());
        $this->service->method('isAvailable')->willReturn(true);
        $this->service->method('estimate')->willThrowException(new ExpressException('We do not deliver to this address.', 'postalAddress'));

        $response = $this->call(['action' => 'start', 'source' => 'cart']);

        self::assertSame([
            'status' => 'error',
            'started' => true,
            'message' => 'We do not deliver to this address.',
            'field' => 'postalAddress',
        ], $response);
    }

    public function testStartErrorsAreShownWithTheirContactField(): void
    {
        $this->service->method('startFromCart')->willThrowException(new ExpressException('Your shopping cart is empty.'));

        $response = $this->call(['action' => 'start', 'source' => 'cart']);

        self::assertSame(['status' => 'error', 'message' => 'Your shopping cart is empty.', 'field' => ''], $response);
    }

    public function testOtherActionsNeedTheExpressCartOfTheSession(): void
    {
        $service = $this->createMock(ExpressCheckoutService::class);
        $service->method('getExpressCart')->willReturn(null);
        $controller = $this->controller(false);
        self::setProperty($controller, 'expressService', $service);

        $response = $this->call(['action' => 'contact'], $controller);

        self::assertSame('Your Apple Pay session expired. Please try again.', $response['message']);
    }

    public function testContactActionEstimatesTheCartForTheSharedAddress(): void
    {
        $this->service->expects(self::once())->method('estimate')
            ->with($this->expressCart, ['countryCode' => 'BE', 'postalCode' => '1000'], null)
            ->willReturn(self::summary());

        $response = $this->call(['action' => 'contact', 'contact' => json_encode(['countryCode' => 'BE', 'postalCode' => '1000'])]);

        self::assertSame(['status' => 'success'] + self::summary(), $response);
    }

    public function testContactActionReportsTheFieldToCorrect(): void
    {
        $this->service->method('estimate')->willThrowException(new ExpressException('We do not deliver to this country.', 'country'));

        $response = $this->call(['action' => 'contact', 'contact' => '{"countryCode":"XX"}']);

        self::assertSame(['status' => 'error', 'message' => 'We do not deliver to this country.', 'field' => 'country'], $response);
    }

    public function testShippingActionChangesTheCarrier(): void
    {
        $this->service->expects(self::once())->method('changeShipping')
            ->with($this->expressCart, '7,')
            ->willReturn(['selectedShipping' => '7,'] + self::summary());

        $response = $this->call(['action' => 'shipping', 'identifier' => '7,']);

        self::assertSame('success', $response['status']);
        self::assertSame('7,', $response['selectedShipping']);
    }

    public function testAuthorizeActionPreparesTheCartAndPointsToTheRequestController(): void
    {
        $payment = ['billingContact' => ['givenName' => 'Anna'], 'shippingContact' => ['countryCode' => 'NL']];
        $this->service->expects(self::once())->method('authorize')
            ->with($this->expressCart, $payment, '5,', 14.45);

        $response = $this->call(['action' => 'authorize', 'payment' => json_encode($payment), 'identifier' => '5,', 'amount' => '14.45']);

        self::assertSame([
            'status' => 'success',
            'action' => 'https://shop.test/module/buckaroo3/request',
            'fields' => ['method' => 'applepay', 'buckaroo_express' => 'applepay'],
        ], $response);
    }

    public function invalidAmounts(): array
    {
        return [
            'missing' => [null],
            'not numeric' => ['12,50'],
            'zero' => ['0'],
            'negative' => ['-3'],
        ];
    }

    /**
     * @dataProvider invalidAmounts
     */
    public function testAuthorizeRejectsAnInvalidAmount($amount): void
    {
        $this->service->expects(self::never())->method('authorize');
        $post = ['action' => 'authorize', 'payment' => '{}'];
        if ($amount !== null) {
            $post['amount'] = $amount;
        }

        $response = $this->call($post);

        self::assertSame('The order total has changed. Please try again.', $response['message']);
    }

    public function testAuthorizeErrorsAreShownInTheSheet(): void
    {
        $this->service->method('authorize')->willThrowException(new ExpressException('Please enter a valid city in Apple Pay.', 'locality'));

        $response = $this->call(['action' => 'authorize', 'payment' => '{}', 'amount' => '10']);

        self::assertSame(['status' => 'error', 'message' => 'Please enter a valid city in Apple Pay.', 'field' => 'locality'], $response);
    }

    public function testUnknownActionsAreABadRequest(): void
    {
        $response = $this->call(['action' => 'refund']);

        self::assertSame(['status' => 'error', 'message' => 'Unknown action', 'field' => ''], $response);
        self::assertSame(400, http_response_code());
    }

    public function testUnexpectedErrorsAreLoggedAndHiddenFromTheShopper(): void
    {
        $this->service->method('estimate')->willThrowException(new \RuntimeException('SQL error near cart'));

        $response = $this->call(['action' => 'contact']);

        self::assertSame(['status' => 'error', 'message' => self::GENERIC_ERROR, 'field' => ''], $response);
        self::assertSame([['Buckaroo Apple Pay express: SQL error near cart', 3]], Stubs::callsTo('PrestaShopLogger::addLog'));
    }

    public function testTheExpressServiceIsBuiltFromTheModulePaymentService(): void
    {
        Stubs::object('Cart', 600, ['id_shop' => 1]);
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_CART} = 600;
        $this->context()->country = new \Country();
        $this->context()->country->contains_states = false;
        \Configuration::updateValue('PS_SHOP_NAME', 'Shop');
        Stubs::returns('Cart->isVirtualCart', true);

        $controller = $this->controller(false);
        $response = $this->call(['action' => 'contact'], $controller);

        self::assertSame('success', $response['status']);
        self::assertSame([], $response['shippingMethods']);
        self::assertInstanceOf(ExpressCheckoutService::class, self::getProperty($controller, 'expressService'));
    }

    public function testAMissingPaymentServiceIsLogged(): void
    {
        $this->module->services['buckaroo.config.api.payment.service'] = new \stdClass();

        $response = $this->call(['action' => 'contact'], $this->controller(false));

        self::assertSame(self::GENERIC_ERROR, $response['message']);
        self::assertSame('Buckaroo Apple Pay express: The Buckaroo payment service is not available.', Stubs::callsTo('PrestaShopLogger::addLog')[0][0]);
    }

    public function testAnotherModuleInstanceIsLogged(): void
    {
        \Module::$instances['buckaroo3'] = new \Module();
        $controller = new \Buckaroo3ApplepayexpressModuleFrontController();

        $response = $this->call(['action' => 'start'], $controller);

        self::assertSame(self::GENERIC_ERROR, $response['message']);
        self::assertSame('Buckaroo Apple Pay express: The Buckaroo module is not loaded.', Stubs::callsTo('PrestaShopLogger::addLog')[0][0]);
    }
}
