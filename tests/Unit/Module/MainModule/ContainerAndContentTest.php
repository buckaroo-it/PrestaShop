<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this file
 *
 *  @author    Buckaroo.nl <plugins@buckaroo.nl>
 *  @copyright Copyright (c) Buckaroo B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace BuckarooTest\Unit\Module\MainModule;

use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReportingConsent;
use BuckarooTest\Stubs;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\Security\Csrf\CsrfToken;

require_once __DIR__ . '/MainModuleTestCase.php';

/**
 * Core service container lookup, CSRF token resolution and the configuration
 * page (getContent) with the error reporting consent prompt.
 */
class ContainerAndContentTest extends MainModuleTestCase
{
    /** @var mixed */
    private $previousKernel;

    /** @var string|null */
    private $previousMethod;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/Fixtures/ContainerAwareBuckaroo3.php';
        $this->previousKernel = $GLOBALS['kernel'] ?? null;
        $this->previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $GLOBALS['kernel'] = null;
        SymfonyContainer::$instance = null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['kernel'] = $this->previousKernel;
        SymfonyContainer::$instance = null;
        if ($this->previousMethod === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->previousMethod;
        }
        parent::tearDown();
    }

    // ----- getCoreServiceContainer ------------------------------------------------

    public function testModuleContainerIsUsedAndCached(): void
    {
        $container = new ContainerDouble();
        /** @var ContainerAwareBuckaroo3 $module */
        $module = $this->createModule([], ContainerAwareBuckaroo3::class);
        $module->moduleContainer = $container;

        self::assertSame($container, $module->getCoreServiceContainer());

        $module->moduleContainer = new ContainerDouble();
        self::assertSame($container, $module->getCoreServiceContainer(), 'the first container found is kept');
    }

    public function testContextControllerContainerIsUsedWhenTheModuleHasNone(): void
    {
        $container = new ContainerDouble();
        /** @var ContainerAwareBuckaroo3 $module */
        $module = $this->createModule([], ContainerAwareBuckaroo3::class);
        $module->moduleContainer = new \RuntimeException('no kernel booted');
        $this->frontController();
        Stubs::returns('Controller->getContainer', $container);

        self::assertSame($container, $module->getCoreServiceContainer());
    }

    public function testKernelContainerIsUsedWhenTheControllerHasNone(): void
    {
        $container = new ContainerDouble();
        $module = $this->createModule();
        $this->context()->controller = new \stdClass();
        $GLOBALS['kernel'] = new KernelDouble($container);

        self::assertSame($container, $module->getCoreServiceContainer());
    }

    public function testKernelContainerIsUsedWhenTheControllerContainerFails(): void
    {
        $container = new ContainerDouble();
        $module = $this->createModule();
        $this->frontController();
        Stubs::throws('Controller->getContainer', new \RuntimeException('not a Symfony page'));
        $GLOBALS['kernel'] = new KernelDouble($container);

        self::assertSame($container, $module->getCoreServiceContainer());
    }

    public function testLegacySymfonyContainerIsTheLastResort(): void
    {
        $container = new ContainerDouble();
        $module = $this->createModule();
        $this->frontController();
        Stubs::returns('Controller->getContainer', 'not a container');
        $GLOBALS['kernel'] = new KernelDouble(new \RuntimeException('kernel not booted'));
        SymfonyContainer::$instance = $container;

        self::assertSame($container, $module->getCoreServiceContainer());
    }

    public function testNoContainerAvailable(): void
    {
        /** @var ContainerAwareBuckaroo3 $module */
        $module = $this->createModule([], ContainerAwareBuckaroo3::class);
        $module->moduleContainer = 'not a container';
        $GLOBALS['kernel'] = new KernelDouble('not a container');
        SymfonyContainer::$instance = new \stdClass();

        self::assertNull($module->getCoreServiceContainer());
    }

    // ----- getContent ------------------------------------------------------------

    public function testConfigurationPageAssignsTheVueAppSettings(): void
    {
        $module = $this->createModule();
        $this->request(['token' => 'admin-modules-token']);

        $html = $module->getContent();

        self::assertSame('rendered:app.tpl', $html);
        self::assertSame(
            [['module:buckaroo3/views/templates/admin/app.tpl']],
            Stubs::callsTo('Smarty->fetch')
        );
        $vars = $this->context()->smarty->getTemplateVars();
        self::assertSame('/modules/buckaroo3/views/js/buckaroo.vue.js', $vars['pathApp']);
        self::assertSame('https://shop.test/', $vars['baseUrl']);
        self::assertSame('https://shop.test/admin/AdminDashboard', $vars['adminUrl']);
        self::assertSame('admin-modules-token', $vars['token']);
        self::assertTrue($vars['errorReportingPrompt']);
        self::assertSame(\Buckaroo3::MODULE_VERSION, $vars['moduleVersion']);
    }

    public function testConfigurationPagePrefersTheSymfonyRequestToken(): void
    {
        $module = $this->createModule();
        $this->request(['_token' => 'sf-token', 'token' => 'legacy-token']);

        $module->getContent();

        self::assertSame('sf-token', $this->context()->smarty->getTemplateVars('token'));
    }

    public function testConfigurationPageWithoutAnyToken(): void
    {
        $module = $this->createModule();
        $this->context()->link = new class extends \Link {
            public function getAdminLink($controller, $withToken = true, $sfRouteParams = [], $params = [])
            {
                return 'https://shop.test/admin-dev/index.php/?controller=' . $controller . '&token=x';
            }
        };

        $module->getContent();

        self::assertSame('', $this->context()->smarty->getTemplateVars('token'));
        self::assertSame('https://shop.test/admin-dev/index.php', $this->context()->smarty->getTemplateVars('adminUrl'));
    }

    public function testCsrfTokenFromTheModuleContainer(): void
    {
        /** @var ContainerAwareBuckaroo3 $module */
        $module = $this->createModule([
            'buckaroo.csrf.token_manager' => $this->tokenManager('csrf-from-module'),
            'prestashop.user_provider' => new ServiceDouble(['getUsername' => 'admin@shop.test']),
        ], ContainerAwareBuckaroo3::class);
        $module->moduleServices = ['buckaroo.csrf.token_manager' => true, 'prestashop.user_provider' => true];
        $this->request(['token' => 'legacy-token']);

        $module->getContent();

        self::assertSame('csrf-from-module', $this->context()->smarty->getTemplateVars('token'));
        self::assertSame(
            [['admin@shop.test']],
            $this->services['buckaroo.csrf.token_manager']->callsTo('getToken')
        );
    }

    public function testCsrfTokenFromTheCoreSecurityTokenManager(): void
    {
        /** @var ContainerAwareBuckaroo3 $module */
        $module = $this->createModule([], ContainerAwareBuckaroo3::class);
        $module->hasError = new \RuntimeException('module container not booted');
        $module->moduleContainer = new ContainerDouble([
            'security.csrf.token_manager' => $this->tokenManager('csrf-from-core'),
            'prestashop.user_provider' => new ServiceDouble(['getUsername' => 'admin']),
        ]);

        $module->getContent();

        self::assertSame('csrf-from-core', $this->context()->smarty->getTemplateVars('token'));
    }

    /**
     * @dataProvider unusableCsrfProvider
     */
    public function testRequestTokenIsUsedWhenNoCsrfTokenCanBeMade(array $coreServices, ?\Throwable $getError): void
    {
        /** @var ContainerAwareBuckaroo3 $module */
        $module = $this->createModule([], ContainerAwareBuckaroo3::class);
        $module->moduleContainer = new ContainerDouble($coreServices, $getError);
        $this->request(['token' => 'legacy-token']);

        $module->getContent();

        self::assertSame('legacy-token', $this->context()->smarty->getTemplateVars('token'));
    }

    public function unusableCsrfProvider(): array
    {
        $throwingManager = new ServiceDouble(['getToken' => function () {
            throw new \RuntimeException('session closed');
        }]);
        $emptyManager = new ServiceDouble(['getToken' => new CsrfToken('id', '')]);
        $user = new ServiceDouble(['getUsername' => 'admin']);

        return [
            'no token manager' => [[], null],
            'no user provider' => [['buckaroo.csrf.token_manager' => $emptyManager], null],
            'token manager throws' => [['buckaroo.csrf.token_manager' => $throwingManager, 'prestashop.user_provider' => $user], null],
            'empty token' => [['buckaroo.csrf.token_manager' => $emptyManager, 'prestashop.user_provider' => $user], null],
            'container get fails' => [['buckaroo.csrf.token_manager' => $emptyManager], new \RuntimeException('broken service')],
        ];
    }

    /**
     * @dataProvider consentProvider
     */
    public function testErrorReportingConsentIsStoredFromThePrompt(
        ?string $method,
        array $post,
        $expectedConfig,
        bool $expectedPrompt
    ): void {
        if ($method === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $method;
        }
        $module = $this->createModule();
        $this->request([], $post);

        $module->getContent();

        self::assertSame($expectedConfig, \Configuration::get(ErrorReportingConsent::CONFIG_KEY));
        self::assertSame($expectedPrompt, $this->context()->smarty->getTemplateVars('errorReportingPrompt'));
    }

    public function consentProvider(): array
    {
        $submit = ['submitBuckarooErrorReporting' => '1'];

        return [
            'accepted' => ['POST', $submit + ['buckaroo_error_reporting' => '1'], '1', false],
            'declined' => ['post', $submit + ['buckaroo_error_reporting' => '0'], '0', false],
            'invalid answer' => ['POST', $submit + ['buckaroo_error_reporting' => 'maybe'], false, true],
            'not a POST request' => ['GET', $submit + ['buckaroo_error_reporting' => '1'], false, true],
            'no request method' => [null, $submit + ['buckaroo_error_reporting' => '1'], false, true],
            'not submitted' => ['POST', ['buckaroo_error_reporting' => '1'], false, true],
        ];
    }

    private function tokenManager(string $value): ServiceDouble
    {
        return new ServiceDouble(['getToken' => function ($id) use ($value) {
            return new CsrfToken($id, $value);
        }]);
    }
}
