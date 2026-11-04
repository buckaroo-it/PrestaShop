<?php

declare(strict_types=1);

namespace BuckarooTest\Unit\Services;

use Buckaroo\PrestaShop\Src\Service\CsrfTokenManagerFactory;
use BuckarooTest\TestCase;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class CsrfTokenManagerFactoryTest extends TestCase
{
    /** @var mixed */
    private $previousKernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousKernel = $GLOBALS['kernel'] ?? null;
        $GLOBALS['kernel'] = null;
        SymfonyContainer::$instance = null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['kernel'] = $this->previousKernel;
        SymfonyContainer::$instance = null;
        parent::tearDown();
    }

    private static function manager(string $name): CsrfTokenManagerInterface
    {
        return new class($name) implements CsrfTokenManagerInterface {
            /** @var string */
            public $name;

            public function __construct(string $name)
            {
                $this->name = $name;
            }

            public function getToken($tokenId)
            {
                return new CsrfToken($tokenId, $this->name);
            }

            public function refreshToken($tokenId)
            {
                return $this->getToken($tokenId);
            }

            public function removeToken($tokenId)
            {
                return null;
            }

            public function isTokenValid(CsrfToken $token)
            {
                return true;
            }
        };
    }

    /**
     * @param mixed $service manager to return, a Throwable to throw, or null when the service is missing
     */
    private static function container($service): ContainerInterface
    {
        return new class($service) implements ContainerInterface {
            /** @var mixed */
            private $service;

            /** @var int */
            public $gets = 0;

            public function __construct($service)
            {
                $this->service = $service;
            }

            public function get($id, $invalidBehavior = 1)
            {
                ++$this->gets;
                if ($this->service instanceof \Throwable) {
                    throw $this->service;
                }

                return $this->service;
            }

            public function has($id)
            {
                return $id === 'security.csrf.token_manager' && $this->service !== null;
            }
        };
    }

    /**
     * @param mixed $container a container, or a Throwable thrown by getContainer()
     */
    private static function kernel($container): KernelInterface
    {
        return new class($container) implements KernelInterface {
            /** @var mixed */
            private $container;

            public function __construct($container)
            {
                $this->container = $container;
            }

            public function getContainer()
            {
                if ($this->container instanceof \Throwable) {
                    throw $this->container;
                }

                return $this->container;
            }
        };
    }

    /**
     * @param mixed $controller
     */
    private function moduleController($controller): void
    {
        $context = new \Context();
        $context->controller = $controller;
        $module = new \Module();
        $module->context = $context;
        \Module::$instances['buckaroo3'] = $module;
    }

    /**
     * @param mixed $container a container, or a Throwable thrown by getContainer()
     */
    private static function controller($container): object
    {
        return new class($container) {
            /** @var mixed */
            private $container;

            public function __construct($container)
            {
                $this->container = $container;
            }

            public function getContainer()
            {
                if ($this->container instanceof \Throwable) {
                    throw $this->container;
                }

                return $this->container;
            }
        };
    }

    public function testKernelContainerIsUsedFirst(): void
    {
        $GLOBALS['kernel'] = self::kernel(self::container(self::manager('kernel')));
        $this->moduleController(self::controller(self::container(self::manager('controller'))));
        SymfonyContainer::$instance = self::container(self::manager('legacy'));

        self::assertSame('kernel', CsrfTokenManagerFactory::create()->name);
    }

    public function testControllerContainerIsUsedWithoutKernel(): void
    {
        $this->moduleController(self::controller(self::container(self::manager('controller'))));
        SymfonyContainer::$instance = self::container(self::manager('legacy'));

        self::assertSame('controller', CsrfTokenManagerFactory::create()->name);
    }

    public function testLegacySymfonyContainerIsTheLastResort(): void
    {
        SymfonyContainer::$instance = self::container(self::manager('legacy'));

        self::assertSame('legacy', CsrfTokenManagerFactory::create()->name);
    }

    public function testContainersWithoutTheServiceOrFailingToResolveItAreSkipped(): void
    {
        $failing = self::container(new \LogicException('broken service'));
        $GLOBALS['kernel'] = self::kernel(self::container(null));
        $this->moduleController(self::controller($failing));
        SymfonyContainer::$instance = self::container(self::manager('legacy'));

        self::assertSame('legacy', CsrfTokenManagerFactory::create()->name);
        self::assertSame(1, $failing->gets, 'the same container is tried only once');
    }

    public function testSharedContainerIsOnlyTriedOnce(): void
    {
        $shared = self::container(new \LogicException('broken service'));
        $GLOBALS['kernel'] = self::kernel($shared);
        $this->moduleController(self::controller($shared));
        SymfonyContainer::$instance = $shared;

        try {
            CsrfTokenManagerFactory::create();
            self::fail('Expected the factory to give up');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('CSRF Token Manager service is not available', $exception->getMessage());
        }
        self::assertSame(1, $shared->gets);
    }

    /**
     * @dataProvider unusableSourcesProvider
     */
    public function testFailsWhenNoSourceProvidesAContainer(callable $arrange): void
    {
        $arrange($this);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CSRF Token Manager service is not available');

        CsrfTokenManagerFactory::create();
    }

    public function unusableSourcesProvider(): array
    {
        return [
            'nothing at all' => [function () {
            }],
            'kernel is not a KernelInterface' => [function () {
                $GLOBALS['kernel'] = new \stdClass();
            }],
            'kernel container fails' => [function () {
                $GLOBALS['kernel'] = self::kernel(new \RuntimeException('booting'));
            }],
            'kernel returns no container' => [function () {
                $GLOBALS['kernel'] = self::kernel(new \stdClass());
            }],
            'context without controller' => [function (self $test) {
                $test->moduleController(null);
            }],
            'controller without getContainer' => [function (self $test) {
                $test->moduleController(new \stdClass());
            }],
            'controller container fails' => [function (self $test) {
                $test->moduleController(self::controller(new \RuntimeException('no container')));
            }],
            'controller returns no container' => [function (self $test) {
                $test->moduleController(self::controller('nope'));
            }],
            'legacy container is not a container' => [function () {
                SymfonyContainer::$instance = new \stdClass();
            }],
        ];
    }
}
