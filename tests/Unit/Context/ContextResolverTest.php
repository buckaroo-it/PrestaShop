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

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Context;

use Buckaroo\PrestaShop\Src\Context\ContextAwareTrait;
use Buckaroo\PrestaShop\Src\Context\ContextResolver;
use PHPUnit\Framework\TestCase;

final class ContextResolverTest extends TestCase
{
    protected function setUp(): void
    {
        \Module::$instances = [];
    }

    protected function tearDown(): void
    {
        \Module::$instances = [];
    }

    public function testExplicitContextIsReturnedAsIs(): void
    {
        $context = new \Context();

        self::assertSame($context, ContextResolver::resolve($context));
        self::assertSame($context, ContextResolver::fromCandidate($context));
    }

    public function testProviderExposingGetContextIsUnwrapped(): void
    {
        $context = new \Context();
        $provider = new class($context) {
            private $context;

            public function __construct($context)
            {
                $this->context = $context;
            }

            public function getContext()
            {
                return $this->context;
            }
        };

        self::assertSame($context, ContextResolver::resolve($provider));
    }

    public function testFallsBackToTheModuleContextWhenNothingUsableIsInjected(): void
    {
        $context = new \Context();
        $module = new \Module();
        $module->context = $context;
        \Module::$instances['buckaroo3'] = $module;

        self::assertSame($context, ContextResolver::resolve(null));
        self::assertSame($context, ContextResolver::resolve(new \stdClass()));
        self::assertSame($context, ContextResolver::fromModule());
    }

    public function testReturnsNullWhenNoContextExistsYet(): void
    {
        self::assertNull(ContextResolver::resolve(null));
        self::assertNull(ContextResolver::resolve('not an object'));
        self::assertNull(ContextResolver::fromModule());
    }

    public function testOnlyObjectsWithTheCoreContextPropertiesQualify(): void
    {
        self::assertTrue(ContextResolver::looksLikeContext(new \Context()));
        self::assertFalse(ContextResolver::looksLikeContext(new \stdClass()));
        self::assertFalse(ContextResolver::looksLikeContext(null));
    }

    public function testTraitResolvesLazilyAndCachesTheResult(): void
    {
        $service = new class {
            use ContextAwareTrait;
        };

        // Nothing available at construction time: the service must not fail.
        self::assertNull($service->getContext());

        // The module context becomes available later in the request lifecycle.
        $context = new \Context();
        $module = new \Module();
        $module->context = $context;
        \Module::$instances['buckaroo3'] = $module;

        self::assertSame($context, $service->getContext());

        // An explicitly injected context always wins over the fallback.
        $injected = new \Context();
        $service->setContext($injected);
        self::assertSame($injected, $service->getContext());
    }
}
