<?php

namespace BuckarooTest\Unit\ExpressAndGiftcard;

use Buckaroo\PrestaShop\Src\Service\BuckarooClickToPayService;
use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;
use BuckarooTest\TestCase;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;

require_once __DIR__ . '/ExpressTestSupport.php';

/**
 * Proxy for the Click to Pay OAuth token: only a genuine checkout request may
 * spend the merchant's credentials.
 *
 * Separate processes: header() and http_response_code() only work before any output.
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class ClickToPayTokenControllerTest extends TestCase
{
    use RunsJsonEndpoints;

    /** @var ExpressTestModule */
    private $module;

    /** @var BuckarooClickToPayService&\PHPUnit\Framework\MockObject\MockObject */
    private $clickToPay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = new ExpressTestModule();
        $this->module->paymentModes['clicktopay'] = true;
        \Module::$instances['buckaroo3'] = $this->module;

        $this->clickToPay = $this->createMock(BuckarooClickToPayService::class);
        $clickToPay = $this->clickToPay;
        SymfonyContainer::$instance = new class($clickToPay) implements ContainerInterface {
            /** @var object */
            private $service;

            public function __construct($service)
            {
                $this->service = $service;
            }

            public function get($id, $invalidBehavior = 1)
            {
                return $this->service;
            }

            public function has($id)
            {
                return $id === 'buckaroo.config.api.clicktopay.service';
            }
        };

        $customer = new \Customer();
        $customer->id = 4;
        $this->context()->customer = $customer;
        $cart = new \Cart();
        $cart->id = 5;
        $cart->id_customer = 4;
        $cart->products = [['id_product' => 1]];
        $this->context()->cart = $cart;

        $this->request(['token' => 'token']);
    }

    protected function tearDown(): void
    {
        SymfonyContainer::$instance = null;
        parent::tearDown();
    }

    private function respond(): array
    {
        $controller = new \Buckaroo3ClickToPayTokenModuleFrontController();
        self::withoutHeaderWarnings(function () use ($controller) {
            $controller->initContent();
        });
        $output = self::jsonOutput($controller);
        self::assertCount(1, $output);

        return $output[0];
    }

    public function testReturnsTheAccessTokenForAGenuineCheckout(): void
    {
        $this->clickToPay->method('isConfigured')->willReturn(true);
        $this->clickToPay->expects(self::once())->method('getAccessToken')
            ->willReturn(['access_token' => 'abc', 'expires_in' => 3600]);

        self::assertSame(['access_token' => 'abc', 'expires_in' => 3600], $this->respond());
        self::assertSame(200, http_response_code());
    }

    public function untrustedRequests(): array
    {
        return [
            'wrong token' => ['token'],
            'no cart' => ['no cart'],
            'unsaved cart' => ['unsaved'],
            'empty cart' => ['empty'],
            'cart without customer' => ['no customer'],
            'cart of another customer' => ['other customer'],
            'Click to Pay disabled' => ['disabled'],
        ];
    }

    /**
     * @dataProvider untrustedRequests
     */
    public function testRejectsRequestsThatAreNotAGenuineCheckout(string $case): void
    {
        $cart = $this->context()->cart;
        switch ($case) {
            case 'token':
                $_GET['token'] = 'forged';
                break;
            case 'no cart':
                $this->context()->cart = null;
                break;
            case 'unsaved':
                $cart->id = null;
                break;
            case 'empty':
                $cart->products = [];
                break;
            case 'no customer':
                $cart->id_customer = 0;
                break;
            case 'other customer':
                $cart->id_customer = 9;
                break;
            default:
                $this->module->paymentModes['clicktopay'] = false;
        }
        $this->clickToPay->expects(self::never())->method('isConfigured');

        self::assertSame(['error' => 'Unauthorized request'], $this->respond());
        self::assertSame(403, http_response_code());
    }

    public function testReportsMissingCredentials(): void
    {
        $this->clickToPay->method('isConfigured')->willReturn(false);
        $this->clickToPay->expects(self::never())->method('getAccessToken');

        self::assertSame(['error' => 'Click to Pay credentials are not configured.'], $this->respond());
        self::assertSame(500, http_response_code());
    }

    public function testReportsAFailedTokenRequest(): void
    {
        $this->clickToPay->method('isConfigured')->willReturn(true);
        $this->clickToPay->method('getAccessToken')->willReturn(null);

        self::assertSame(['error' => 'Failed to obtain access token.'], $this->respond());
        self::assertSame(500, http_response_code());
    }

    public function testBuildsTheServiceFromTheModuleConfigWithoutContainer(): void
    {
        SymfonyContainer::$instance = null;
        $config = $this->createMock(BuckarooConfigService::class);
        $config->expects(self::atLeastOnce())->method('getConfigValue')
            ->with('clicktopay', 'client_id')
            ->willReturn('  ');
        $this->module->services['buckaroo.config.api.config.service'] = $config;

        self::assertSame(['error' => 'Click to Pay credentials are not configured.'], $this->respond());
        self::assertSame(500, http_response_code());
    }
}
