<?php
/**
 * Base class for module unit tests that run code against the PrestaShop stubs.
 *
 * Resets every stub registry and PrestaShop global before each test, and adds
 * helpers for the patterns that come up across controllers, checkouts and
 * services.
 */

namespace BuckarooTest;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Stubs::reset();
    }

    protected function tearDown(): void
    {
        Stubs::reset();
        parent::tearDown();
    }

    /** Shorthand for the shared stub context. */
    protected function context(): \Context
    {
        return \Context::getContext();
    }

    /**
     * Run $callback and assert it redirects (Tools::redirect, redirectWithNotifications, ...).
     *
     * @return string the redirect target
     */
    protected function expectRedirect(callable $callback, ?string $urlContains = null): string
    {
        try {
            $callback();
        } catch (RedirectException $redirect) {
            if ($urlContains !== null) {
                self::assertStringContainsString($urlContains, $redirect->url);
            }

            return $redirect->url;
        }

        self::fail('Expected a redirect' . ($urlContains !== null ? ' to a URL containing "' . $urlContains . '"' : ''));
    }

    /**
     * Run $callback and assert the request was ended with ajaxDie().
     *
     * @return mixed the payload passed to ajaxDie()
     */
    protected function expectTerminate(callable $callback)
    {
        try {
            $callback();
        } catch (TerminateException $terminate) {
            return $terminate->output;
        }

        self::fail('Expected the request to be terminated');
    }

    /** Set request parameters the way PrestaShop reads them (Tools::getValue). */
    protected function request(array $get = [], array $post = []): void
    {
        $_GET = $get;
        $_POST = $post;
    }

    /** Read a private/protected property. */
    protected static function getProperty(object $object, string $name)
    {
        $reflection = new \ReflectionProperty($object, $name);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        return $reflection->getValue($object);
    }

    /** Write a private/protected property. */
    protected static function setProperty(object $object, string $name, $value): void
    {
        $reflection = new \ReflectionProperty($object, $name);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }
        $reflection->setValue($object, $value);
    }

    /** Call a private/protected method. */
    protected static function invoke(object $object, string $method, array $args = [])
    {
        $reflection = new \ReflectionMethod($object, $method);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        return $reflection->invokeArgs($object, $args);
    }

    /** Create an object without running its constructor (for controllers with heavy constructors). */
    protected static function instantiate(string $class): object
    {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}
