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

// ---- Fixtures ----
// The PrestaShop classes (Order, OrderPayment, Customer, Link, Db, ...) come from
// tests/Support/PrestaShopStubs.php. These helpers register the shop data the
// refund tests expect; call buckaroo_refund_fixtures() from setUp().

if (!function_exists('buckaroo_refund_fixtures')) {
    function buckaroo_refund_fixtures(): void
    {
        BuckarooTest\Stubs::object('Order', 326, [
            'id_cart' => 55,
            'id_currency' => 1,
            'id_customer' => 28,
            'reference' => 'GGADEYTRM',
            'total_paid' => 28.80,
            'module' => 'buckaroo3',
        ]);
        BuckarooTest\Stubs::object('Customer', 28, [
            'email' => 'buyer@example.com',
            'lastname' => 'Kastrati',
        ]);
        BuckarooTest\Stubs::db('/bk_giftcards/i', [
            ['code' => 'boekenbon'],
            ['code' => 'fashionucadeaukaart'],
            ['code' => 'fashioncheque'],
            ['code' => 'vvvgiftcard'],
            ['code' => 'webshopgiftcard'],
            ['code' => 'digitalebioscoopbon'],
            ['code' => 'yourgift'],
        ]);
    }
}

if (!function_exists('buckaroo_refund_payment')) {
    /** An OrderPayment as PrestaShop stores it for the fixture order. */
    function buckaroo_refund_payment(string $method, float $amount = 28.80): OrderPayment
    {
        $payment = new OrderPayment();
        $payment->order_reference = 'GGADEYTRM';
        $payment->id_currency = 1;
        $payment->transaction_id = 'ABCDEF0123456789ABCDEF0123456789';
        $payment->amount = $amount;
        $payment->payment_method = $method;
        $payment->conversion_rate = 1;

        return $payment;
    }
}
