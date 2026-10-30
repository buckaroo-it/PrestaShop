<?php

/**
 * Simple PHPUnit bootstrap for the Buckaroo PrestaShop module.
 *
 * Inspired by the Mollie PrestaShop test setup:
 * https://github.com/mollie/PrestaShop/tree/master/tests
 */

// Fake PrestaShop constants so module classes don't early-exit
if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '9.0.1.0');
}

// The legacy checkout/payment classes include their dependencies through
// _PS_MODULE_DIR_ . 'buckaroo3/...'. The unit tests replace those dependencies
// with the lightweight stubs below, so the constant must point to an isolated,
// empty directory: if it pointed at the real modules directory the real
// classes would be declared next to the stubs ("Cannot declare class Checkout").
if (!defined('_PS_MODULE_DIR_')) {
    $buckarooTestModuleDir = sys_get_temp_dir() . '/buckaroo3-phpunit-modules/';
    if (!is_dir($buckarooTestModuleDir)) {
        @mkdir($buckarooTestModuleDir, 0777, true);
    }
    define('_PS_MODULE_DIR_', $buckarooTestModuleDir);
}

// CoreLogger writes below _PS_ROOT_DIR_ . '/var/logs/buckaroo3/'. Keep the log
// files the tests produce out of any real tree.
if (!defined('_PS_ROOT_DIR_')) {
    define('_PS_ROOT_DIR_', sys_get_temp_dir() . '/buckaroo3-phpunit-root');
}

// Shop base URI (root installation in tests; '/shop/' when installed in a subdirectory)
if (!defined('__PS_BASE_URI__')) {
    define('__PS_BASE_URI__', '/');
}

// Make sure we are in the module root when running tests
chdir(__DIR__ . '/..');

/*
 * Lightweight stubs for legacy module dependencies that rely on a full
 * PrestaShop runtime. For isolated unit tests we only need the symbols
 * to exist so the production code can be loaded safely.
 */
if (!class_exists('Checkout')) {
    class Checkout
    {
        public function setCheckout()
        {
            // No-op in tests; concrete subclasses can still override.
        }
    }
}

if (!class_exists('CarrierHandler')) {
    class CarrierHandler
    {
        public function __construct($cart = null)
        {
        }

        public function handleSendCloud()
        {
            // In unit tests we assume no external carrier integration.
            return null;
        }
    }
}

// Composer autoload for the module and its vendors
require_once __DIR__ . '/../vendor/autoload.php';

/*
 * Lightweight test doubles for common PrestaShop classes used by the module.
 * These keep the unit tests isolated from a real PrestaShop runtime while
 * still exercising the module logic.
 */

// ---- Global Context / Tools / Currency stubs ----
if (!class_exists('Context')) {
    class Context
    {
        public $currency;
        public $shop;
        public $language;
        public $country;
        public $cookie;
        public $link;
        public $cart;
        public $customer;
        public $controller;

        private static $instance;

        public static function getContext()
        {
            if (!self::$instance) {
                self::$instance = new self();
                self::$instance->currency = new Currency();
            }

            return self::$instance;
        }
    }
}

// ---- Module registry stub (ContextResolver falls back to the module instance) ----
if (!class_exists('Module')) {
    class Module
    {
        /** @var array<string, object> */
        public static $instances = [];

        public $context;

        public static function getInstanceByName($name)
        {
            return self::$instances[$name] ?? false;
        }

        /** Mirrors Buckaroo3::getModuleContext(); Module::$context is protected in the core. */
        public function getModuleContext()
        {
            return $this->context;
        }
    }
}

if (!class_exists('Currency')) {
    class Currency
    {
        public $iso_code = 'EUR';

        /** @var array<int,string> */
        public static $isoById = [];

        /**
         * ISO code for a currency id. Mirrors PrestaShop's Currency::getIsoCodeById()
         * so refund request builders can resolve the order currency in unit tests.
         */
        public static function getIsoCodeById($id)
        {
            return self::$isoById[(int) $id] ?? 'EUR';
        }

        public function __construct($id = null)
        {
            // Allow tests to control the currency per ID while keeping a sane default.
            if ($id !== null && isset(self::$isoById[(int) $id])) {
                $this->iso_code = self::$isoById[(int) $id];
            } else {
                $this->iso_code = 'EUR';
            }
        }
    }
}

if (!class_exists('Tools')) {
    class Tools
    {
        public static function getContextLocale($context)
        {
            return new class {
                public function formatPrice($amount, $isoCode)
                {
                    // Simple, deterministic representation for assertions
                    return sprintf('%s %.2f', $isoCode, $amount);
                }
            };
        }

        public static function strtoupper($string)
        {
            return strtoupper($string);
        }

        public static function strtolower($string)
        {
            return strtolower($string);
        }

        /** Mirrors PrestaShop: POST first, then GET. */
        public static function getValue($key, $defaultValue = false)
        {
            if (isset($_POST[$key])) {
                return $_POST[$key];
            }

            return $_GET[$key] ?? $defaultValue;
        }

        public static function isSubmit($submit)
        {
            return isset($_POST[$submit]) || isset($_GET[$submit]);
        }
    }
}

if (!class_exists('Configuration')) {
    class Configuration
    {
        /** @var array<string,mixed> */
        public static $values = [];

        public static function get($key)
        {
            return self::$values[$key] ?? false;
        }

        public static function updateValue($key, $value)
        {
            self::$values[$key] = $value;

            return true;
        }

        public static function deleteByName($key)
        {
            unset(self::$values[$key]);

            return true;
        }
    }
}

if (!class_exists('Validate')) {
    class Validate
    {
        public static function isLoadedObject($object)
        {
            return $object !== null;
        }
    }
}

if (!class_exists('Country')) {
    class Country
    {
        /** @var array<int,string> */
        public static $isoById = [];

        public $iso_code = 'NL';

        public function __construct($id = null)
        {
            if ($id !== null && isset(self::$isoById[(int) $id])) {
                $this->iso_code = self::$isoById[(int) $id];
            }
        }

        public static function getIsoById($id)
        {
            return self::$isoById[(int) $id] ?? null;
        }

        /** @var array<int, array<string, mixed>> rows returned by getCountries() */
        public static $countries = [];

        /** @var int|null language id passed to the last getCountries() call */
        public static $lastLanguageId;

        public static function getCountries($idLang, $active = false, $containStates = false, $listStates = false)
        {
            self::$lastLanguageId = (int) $idLang;

            return self::$countries;
        }
    }
}

// ---- Namespaced PaymentOption stub ----
if (!class_exists('\PrestaShop\PrestaShop\Core\Payment\PaymentOption')) {
    eval('
    namespace PrestaShop\PrestaShop\Core\Payment;

    class PaymentOption
    {
        private $moduleName;
        private $callToActionText;
        private $action;
        private $inputs = [];
        private $form;
        private $logo;

        public function setCallToActionText($text)
        {
            $this->callToActionText = $text;
            return $this;
        }

        public function setAction($action)
        {
            $this->action = $action;
            return $this;
        }

        public function setModuleName($name)
        {
            $this->moduleName = $name;
            return $this;
        }

        public function setInputs(array $inputs)
        {
            $this->inputs = $inputs;
            return $this;
        }

        public function setForm($form)
        {
            $this->form = $form;
            return $this;
        }

        public function setLogo($logo)
        {
            $this->logo = $logo;
            return $this;
        }

        public function getModuleName()
        {
            return $this->moduleName;
        }

        public function getCallToActionText()
        {
            return $this->callToActionText;
        }

        public function getAction()
        {
            return $this->action;
        }

        public function getForm()
        {
            return $this->form;
        }

        public function getInputs()
        {
            return $this->inputs;
        }

        public function getLogo()
        {
            return $this->logo;
        }
    }
    ');
}

// ---- Minimal Doctrine ORM EntityManager stub ----
// The module services type-hint Doctrine\ORM\EntityManager, but for isolated
// unit tests we only need the symbol to exist so that anonymous subclasses
// in the tests can extend it and provide an in-memory repository.
if (!class_exists('\Doctrine\ORM\EntityManager')) {
    eval('
    namespace Doctrine\ORM;

    abstract class EntityManager
    {
        /**
         * In production this would return a Doctrine repository. For unit tests
         * we never call this implementation because test doubles override it.
         *
         * @param string $className
         * @return mixed
         */
        public function getRepository($className)
        {
            return null;
        }
    }
    ');
}
