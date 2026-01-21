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

// Point to the modules directory (one level above the module root)
if (!defined('_PS_MODULE_DIR_')) {
    define('_PS_MODULE_DIR_', dirname(__DIR__, 2) . '/');
}

// Make sure we are in the module root when running tests
chdir(__DIR__ . '/..');

/**
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

/**
 * Lightweight test doubles for common PrestaShop classes used by the module.
 * These keep the unit tests isolated from a real PrestaShop runtime while
 * still exercising the module logic.
 */

// ---- Global Context / Tools / Currency stubs ----
if (!class_exists('Context')) {
    class Context
    {
        public $currency;

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

if (!class_exists('Currency')) {
    class Currency
    {
        public $iso_code = 'EUR';

        /** @var array<int,string> */
        public static $isoById = [];

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


