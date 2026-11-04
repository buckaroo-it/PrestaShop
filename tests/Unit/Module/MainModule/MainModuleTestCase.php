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

use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/StubsSupport.php';

/**
 * Shared setup for the Buckaroo3 module tests: a real module instance whose
 * service container (Module::get()) answers from $this->services.
 */
abstract class MainModuleTestCase extends TestCase
{
    /** @var array<string, mixed> service id => double (a \Throwable is thrown) */
    protected $services = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Loaded here rather than at file load so the coverage run sees the file header.
        require_once _PS_MODULE_DIR_ . 'buckaroo3/buckaroo3.php';
        $this->services = [];
    }

    /**
     * Build the module through its real constructor and route Module::get() to $this->services.
     *
     * @return \Buckaroo3
     */
    protected function createModule(array $services = [], string $class = \Buckaroo3::class)
    {
        $this->services = $services + $this->services;
        /** @var \Buckaroo3 $module */
        $module = new $class();
        $module->stub('get', function ($id) {
            if (!array_key_exists($id, $this->services)) {
                throw new \RuntimeException('Unknown service ' . $id);
            }
            if ($this->services[$id] instanceof \Throwable) {
                throw $this->services[$id];
            }

            return $this->services[$id];
        });
        $module->logger = new ServiceDouble();

        return $module;
    }

    /** Config service double answering getConfigArrayForMethod()/getConfigValue() from a per-method map. */
    protected function configService(array $configByMethod = [], array $extra = []): ServiceDouble
    {
        return new ServiceDouble($extra + [
            'getConfigArrayForMethod' => function ($method) use ($configByMethod) {
                return $configByMethod[$method] ?? [];
            },
            'getConfigValue' => function ($method, $key) use ($configByMethod) {
                return $configByMethod[$method][$key] ?? null;
            },
        ]);
    }

    /** Front controller registered on the context; collects JS/CSS in ->assets. */
    protected function frontController(string $phpSelf = 'order'): \FrontController
    {
        $controller = new \FrontController();
        $controller->php_self = $phpSelf;
        $this->context()->controller = $controller;

        return $controller;
    }

    /** Loaded cart on the context. */
    protected function contextCart(int $id = 7, float $total = 100.0): \Cart
    {
        $cart = new \Cart();
        $cart->id = $id;
        $cart->stub('getOrderTotal', $total);
        $this->context()->cart = $cart;

        return $cart;
    }

    /** Gift card rows for bk_group_transaction (and their names in bk_giftcards). */
    protected function giftcardRows(array $rows, array $names = []): void
    {
        Stubs::db('/bk_group_transaction/', $rows);
        Stubs::db('/FROM `ps_bk_giftcards`/', function ($sql) use ($names) {
            foreach ($names as $code => $name) {
                if (strpos($sql, "'" . $code . "'") !== false) {
                    return ['name' => $name];
                }
            }

            return false;
        });
    }

    /** @return array<string, mixed> merged arguments of every Media::addJsDef() call */
    protected function jsDefs(): array
    {
        $defs = [];
        foreach (Stubs::callsTo('Media::addJsDef') as $args) {
            $defs = array_merge($defs, $args[0]);
        }

        return $defs;
    }

    /** @return array<string, array> registered JS by id => [path, params] */
    protected function registeredJs(\Controller $controller): array
    {
        $js = [];
        foreach ($controller->assets as $asset) {
            if ($asset[0] === 'js' && isset($asset[2])) {
                $js[$asset[1]] = [$asset[2], $asset[3]];
            }
        }

        return $js;
    }

    /** @return array<int, string> CSS paths added with addCSS() */
    protected function addedCss(\Controller $controller): array
    {
        $css = [];
        foreach ($controller->assets as $asset) {
            if ($asset[0] === 'css' && count($asset) === 2) {
                $css[] = $asset[1];
            }
        }

        return $css;
    }

    /** @return array<int, string> messages passed to PrestaShopLogger::addLog() */
    protected function loggedMessages(): array
    {
        return array_map(function ($args) {
            return (string) $args[0];
        }, Stubs::callsTo('PrestaShopLogger::addLog'));
    }
}
