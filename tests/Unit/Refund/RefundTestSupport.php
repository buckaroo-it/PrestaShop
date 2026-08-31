<?php

declare(strict_types=1);

/**
 * Shared lightweight stubs for the Buckaroo refund unit tests.
 *
 * The module test-suite runs in isolation (composer install of the module +
 * phpunit, without a full PrestaShop / Symfony / Doctrine runtime — see
 * .github/workflows/tests.yml). These stubs provide just enough of the
 * PrestaShop and framework symbols that the refund request/response code
 * touches, so the production classes can be exercised without a live shop.
 *
 * Every definition is guarded with class_exists() so that, if the tests are
 * ever run inside a fuller environment, the real implementations win.
 */

// ---- Symfony HttpFoundation Request (used by AbstractBuilder::getIp) ----
if (!class_exists('Symfony\\Component\\HttpFoundation\\Request')) {
    eval('
    namespace Symfony\\Component\\HttpFoundation;
    class Request
    {
        public static function createFromGlobals()
        {
            return new self();
        }

        public function getClientIp()
        {
            return $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";
        }
    }
    ');
}

// ---- PrestaShop OrderException (thrown by the refund response handler) ----
if (!class_exists('PrestaShop\\PrestaShop\\Core\\Domain\\Order\\Exception\\OrderException')) {
    eval('
    namespace PrestaShop\\PrestaShop\\Core\\Domain\\Order\\Exception;
    class OrderException extends \\Exception
    {
    }
    ');
}

// ---- Global PrestaShop class stubs ----
if (!class_exists('Order')) {
    class Order
    {
        public $id = 326;
        public $id_cart = 55;
        public $id_currency = 1;
        public $id_customer = 28;
        public $reference = 'GGADEYTRM';
        public $total_paid = 28.80;
        public $module = 'buckaroo3';

        public function __construct($id = null)
        {
            if ($id !== null) {
                $this->id = (int) $id;
            }
        }
    }
}

if (!class_exists('OrderPayment')) {
    class OrderPayment
    {
        public $order_reference = 'GGADEYTRM';
        public $id_currency = 1;
        public $transaction_id = 'ABCDEF0123456789ABCDEF0123456789';
        public $amount = 28.80;
        public $payment_method = 'fashioncheque';
        public $conversion_rate = 1;

        public function save()
        {
            return true;
        }
    }
}

if (!class_exists('Customer')) {
    class Customer
    {
        public $email = 'buyer@example.com';
        public $lastname = 'Kastrati';

        public function __construct($id = null)
        {
        }
    }
}

if (!class_exists('Link')) {
    class Link
    {
        public function getModuleLink($module, $controller, array $params = [], $ssl = false)
        {
            return 'https://shop.test/module/' . $module . '/' . $controller;
        }
    }
}

if (!class_exists('DbQuery')) {
    class DbQuery
    {
        public function select($fields)
        {
            return $this;
        }

        public function from($table)
        {
            return $this;
        }

        public function where($condition)
        {
            return $this;
        }
    }
}

if (!class_exists('Db')) {
    class Db
    {
        /** @var Db|null */
        private static $instance;

        /** @var array<int,array<string,string>> Rows returned by executeS(); overridable by tests. */
        public static $giftcards = [
            ['code' => 'boekenbon'],
            ['code' => 'fashionucadeaukaart'],
            ['code' => 'fashioncheque'],
            ['code' => 'vvvgiftcard'],
            ['code' => 'webshopgiftcard'],
            ['code' => 'digitalebioscoopbon'],
            ['code' => 'yourgift'],
        ];

        public static function getInstance()
        {
            if (!self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function executeS($query)
        {
            return self::$giftcards;
        }

        public function insert($table, $data)
        {
            return true;
        }

        public function execute($sql)
        {
            return true;
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
    }
}
