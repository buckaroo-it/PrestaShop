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

namespace BuckarooTest\Unit\Data;

use Buckaroo\PrestaShop\Src\Container\ContainerAwareTrait;
use BuckarooTest\TestCase;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service lookup through the PrestaShop Symfony container, or a booted legacy kernel.
 */
class ContainerAwareTraitTest extends TestCase
{
    protected function tearDown(): void
    {
        SymfonyContainer::$instance = null;
        parent::tearDown();
    }

    /** @param array<string, object> $services */
    private static function container(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /** @var array<string, object> */
            public $services;

            public function __construct(array $services)
            {
                $this->services = $services;
            }

            public function get($id, $invalidBehavior = 1)
            {
                if (!isset($this->services[$id])) {
                    throw new \RuntimeException('Unknown service ' . $id);
                }

                return $this->services[$id];
            }

            public function has($id)
            {
                return isset($this->services[$id]);
            }
        };
    }

    private static function consumer()
    {
        return new class {
            use ContainerAwareTrait;

            public function hasPublic(string $id): bool
            {
                return $this->hasService($id);
            }

            public function getPublic(string $id)
            {
                return $this->getService($id);
            }
        };
    }

    public function testServicesComeFromTheSymfonyContainer(): void
    {
        $service = new \stdClass();
        SymfonyContainer::$instance = self::container(['buckaroo.service' => $service]);
        $consumer = self::consumer();

        self::assertTrue($consumer->hasPublic('buckaroo.service'));
        self::assertFalse($consumer->hasPublic('other'));
        self::assertSame($service, $consumer->getPublic('buckaroo.service'));
    }

    public function testTheResolvedContainerIsKept(): void
    {
        $first = new \stdClass();
        SymfonyContainer::$instance = self::container(['svc' => $first]);
        $consumer = self::consumer();
        $consumer->getPublic('svc');

        SymfonyContainer::$instance = self::container(['svc' => new \stdClass()]);

        self::assertSame($first, $consumer->getPublic('svc'));
    }

    public function testGetServicePropagatesContainerErrors(): void
    {
        SymfonyContainer::$instance = self::container([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown service missing');

        self::consumer()->getPublic('missing');
    }

    /**
     * Without a Symfony container nor a kernel class, hasService() swallows the error
     * and getService() reports it.
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testWithoutAnyKernelTheLookupFails(): void
    {
        if (class_exists('\\FrontKernel') || class_exists('\\AppKernel')) {
            self::markTestSkipped('a kernel class is already loaded');
        }
        $consumer = self::consumer();

        self::assertFalse($consumer->hasPublic('svc'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to locate an instantiable PrestaShop kernel class.');
        $consumer->getPublic('svc');
    }

    /**
     * PrestaShop 9 ships an abstract FrontKernel next to AppKernel: the first
     * instantiable one is booted once and its container used.
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testTheFirstInstantiableLegacyKernelIsBootedOnce(): void
    {
        if (class_exists('\\FrontKernel') || class_exists('\\AppKernel')) {
            self::markTestSkipped('a kernel class is already loaded');
        }
        eval('
            abstract class FrontKernel {}
            class AppKernel
            {
                public static $created = [];
                public static $boots = 0;
                public static $container;
                public function __construct($env, $debug) { self::$created[] = [$env, $debug]; }
                public function boot() { ++self::$boots; }
                public function getContainer() { return self::$container; }
            }
        ');
        $service = new \stdClass();
        \AppKernel::$container = self::container(['svc' => $service]);

        // Both consumers are instances of the same class, so they share the static kernel.
        $first = self::consumer();
        $second = self::consumer();

        self::assertSame($service, $first->getPublic('svc'));
        self::assertTrue($second->hasPublic('svc'));
        self::assertSame([['prod', false]], \AppKernel::$created);
        self::assertSame(1, \AppKernel::$boots);
    }
}
