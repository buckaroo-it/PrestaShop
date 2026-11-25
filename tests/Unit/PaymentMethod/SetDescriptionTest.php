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

namespace Buckaroo\PrestaShop\Tests\Unit\PaymentMethod;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../api/paymentmethods/paymentmethod.php';

/**
 * Concrete test double for exercising PaymentMethod::setDescription().
 */
final class SetDescriptionPaymentMethod extends \PaymentMethod
{
}

/**
 * PaymentMethod::setDescription() runs in both order-first and order-after-payment
 * flows. When no Order exists for the cart yet, it must not read properties on
 * null and must fall back cleanly to the generated invoice/order reference.
 */
final class SetDescriptionTest extends TestCase
{
    /** @var mixed */
    private $previousLabel;

    protected function setUp(): void
    {
        $this->previousLabel = \Configuration::$values['BUCKAROO_TRANSACTION_LABEL'] ?? null;
        \Order::$byCartId = [];
    }

    protected function tearDown(): void
    {
        \Order::$byCartId = [];
        if ($this->previousLabel === null) {
            unset(\Configuration::$values['BUCKAROO_TRANSACTION_LABEL']);
        } else {
            \Configuration::$values['BUCKAROO_TRANSACTION_LABEL'] = $this->previousLabel;
        }
    }

    public function testOrderAfterPaymentWithoutOrderUsesInvoiceIdWithoutWarnings(): void
    {
        \Configuration::$values['BUCKAROO_TRANSACTION_LABEL'] = 'Order {order_number} at {shop_name} {id_customer}';

        $method = $this->createPaymentMethod('Test Shop');
        $method->invoiceId = 'ABC123456_42';
        $method->orderId = 'FALLBACK_42';

        $this->callSetDescriptionWithoutWarnings($method, 42);

        self::assertSame('Order ABC123456_42 at Test Shop', $method->description);
    }

    public function testOrderAfterPaymentWithoutInvoiceOrOrderIdFallsBackToCartId(): void
    {
        \Configuration::$values['BUCKAROO_TRANSACTION_LABEL'] = 'Order {order_number} - {shop_name}';

        $method = $this->createPaymentMethod('Test Shop');

        $this->callSetDescriptionWithoutWarnings($method, 99);

        self::assertSame('Order 99 - Test Shop', $method->description);
    }

    public function testOrderFirstFlowUsesOrderReferenceAndExtraOrderProperties(): void
    {
        \Configuration::$values['BUCKAROO_TRANSACTION_LABEL'] = 'Order #{id} ({order_number}) - {shop_name}';

        $order = new \Order(326);
        $order->reference = 'GGADEYTRM';
        \Order::$byCartId[55] = $order;

        $method = $this->createPaymentMethod('Test Shop');
        $method->invoiceId = 'IGNORED_55';

        $this->callSetDescriptionWithoutWarnings($method, 55);

        self::assertSame('Order #326 (GGADEYTRM) - Test Shop', $method->description);
    }

    public function testStaticDescriptionWithoutPlaceholdersIsPreserved(): void
    {
        \Configuration::$values['BUCKAROO_TRANSACTION_LABEL'] = 'Webshop purchase';

        $method = $this->createPaymentMethod('Test Shop');

        $this->callSetDescriptionWithoutWarnings($method, 12);

        self::assertSame('Webshop purchase', $method->description);
    }

    private function createPaymentMethod(string $shopName): SetDescriptionPaymentMethod
    {
        $context = new \Context();
        $context->shop = (object) ['name' => $shopName];

        $method = new SetDescriptionPaymentMethod();
        $method->setContext($context);

        return $method;
    }

    private function callSetDescriptionWithoutWarnings(SetDescriptionPaymentMethod $method, int $cartId): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);

        try {
            $method->setDescription($cartId);
        } finally {
            restore_error_handler();
        }
    }
}
