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

use Buckaroo\PrestaShop\Src\ApplePay\ExpressCheckoutService;
use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentService;
use BuckarooTest\Stubs;

require_once __DIR__ . '/MainModuleTestCase.php';

/**
 * Order life cycle hooks: confirmation page, payment return, confirmation
 * e-mail and order status updates.
 */
class OrderHooksTest extends MainModuleTestCase
{
    // ----- hookDisplayOrderConfirmation -------------------------------------------

    public function testOrderConfirmationWithoutOrder(): void
    {
        self::assertSame('', $this->createModule()->hookDisplayOrderConfirmation([]));
    }

    public function testOrderConfirmationWithoutFee(): void
    {
        $order = $this->order(20);

        self::assertSame('', $this->createModule()->hookDisplayOrderConfirmation(['order' => $order]));
        self::assertSame(['SELECT * FROM ps_bk_buckaroo_fee WHERE id_order = 20'], Stubs::sql('getRow'));
    }

    /**
     * @dataProvider orderParamProvider
     */
    public function testOrderConfirmationShowsTheFeeIncludingTax(string $param): void
    {
        $order = $this->order(20);
        Stubs::object('Cart', 8, ['id_address_invoice' => 4]);
        Stubs::object('Address', 4, ['city' => 'Utrecht']);
        Stubs::db('/bk_buckaroo_fee/', ['buckaroo_fee_tax_excl' => '10']);
        $this->taxRate(21.0);
        \Configuration::updateValue('BUCKAROO_PAYMENT_FEE_FRONTEND_LABEL', 'Betaalkosten');

        $html = $this->createModule()->hookDisplayOrderConfirmation([$param => $order]);

        self::assertSame('display:views/templates/hook/order-confirmation-fee.tpl', $html);
        self::assertSame('EUR 12.10', $this->context()->smarty->getTemplateVars('orderBuckarooFee'));
        self::assertSame('Betaalkosten', $this->context()->smarty->getTemplateVars('paymentFeeLabel'));
        self::assertSame(4, Stubs::callsTo('TaxManagerFactory::getManager')[0][0]->id);
    }

    public function orderParamProvider(): array
    {
        return ['PrestaShop 1.7+' => ['order'], 'legacy' => ['objOrder']];
    }

    public function testOrderConfirmationRestoresTheShopperCartAfterApplePayExpress(): void
    {
        $order = $this->order(20);
        $cookie = $this->context()->cookie;
        $cookie->__set(ExpressCheckoutService::COOKIE_CART, 8);
        $cookie->__set(ExpressCheckoutService::COOKIE_RESTORE_CART, 3);
        $cookie->__set('id_cart', 8);
        Stubs::object('Cart', 3, ['id_customer' => 0, 'products' => [['id_product' => 1]]]);
        $module = $this->createModule([
            'buckaroo.config.api.payment.service' => $this->createMock(BuckarooPaymentService::class),
        ]);

        $module->hookDisplayOrderConfirmation(['order' => $order]);

        self::assertSame(3, $cookie->__get('id_cart'));
        self::assertFalse($cookie->__get(ExpressCheckoutService::COOKIE_CART));
        self::assertSame(1, $cookie->writes);
    }

    public function testOrderConfirmationSkipsTheRestoreWithoutPaymentService(): void
    {
        $order = $this->order(20);
        $this->context()->cookie->__set(ExpressCheckoutService::COOKIE_CART, 8);
        $module = $this->createModule(['buckaroo.config.api.payment.service' => new ServiceDouble()]);

        self::assertSame('', $module->hookDisplayOrderConfirmation(['order' => $order]));
        self::assertSame(8, $this->context()->cookie->__get(ExpressCheckoutService::COOKIE_CART));
    }

    public function testOrderConfirmationLogsRestoreErrors(): void
    {
        $order = $this->order(20);
        $this->context()->cookie->__set(ExpressCheckoutService::COOKIE_CART, 8);
        $module = $this->createModule(['buckaroo.config.api.payment.service' => new \RuntimeException('container down')]);

        self::assertSame('', $module->hookDisplayOrderConfirmation(['order' => $order]));
        self::assertSame(['Buckaroo: Apple Pay express cart restore - container down'], $this->loggedMessages());
    }

    // ----- hookPaymentReturn -----------------------------------------------------

    public function testPaymentReturnDoesNothingWhenInactive(): void
    {
        $module = $this->createModule();
        $module->active = false;
        $this->request(['response_received' => '1']);

        self::assertNull($module->hookPaymentReturn([]));
    }

    public function testPaymentReturnForATransferShowsTheBankMessage(): void
    {
        $this->paidOrder(30, 49.95);
        $customer = new \Customer();
        $customer->id = 3;
        $this->context()->customer = $customer;
        $this->context()->cookie->HtmlText = 'Transfer to NL00BANK';
        $this->request(['response_received' => 'transfer', 'id_order' => '30']);

        $html = $this->createModule()->hookPaymentReturn([]);

        self::assertSame('display:payment_return_redirectsuccess.tpl', $html);
        $vars = $this->context()->smarty->getTemplateVars();
        self::assertFalse($vars['is_guest']);
        self::assertSame(30, $vars['order']->id);
        self::assertSame('EUR 49.95', $vars['price']);
        self::assertSame('Transfer to NL00BANK', $vars['message']);
    }

    public function testPaymentReturnForASuccessfulPayment(): void
    {
        $this->paidOrder(30, 10.0);
        $this->context()->customer = new \Customer();
        $this->request(['id_order' => '30', 'success' => '1']);

        $html = $this->createModule()->hookPaymentReturn([]);

        self::assertSame('display:payment_return_success.tpl', $html);
        $vars = $this->context()->smarty->getTemplateVars();
        self::assertTrue($vars['is_guest']);
        self::assertSame('EUR 10.00', $vars['price']);
        self::assertArrayNotHasKey('message', $vars);
    }

    public function testPaymentReturnWithoutResultRedirectsToTheErrorPage(): void
    {
        $module = $this->createModule();
        $this->request(['id_order' => '30']);

        $this->expectRedirect(function () use ($module) {
            $module->hookPaymentReturn([]);
        }, 'controller=error');
    }

    // ----- hookActionEmailSendBefore ---------------------------------------------

    public function testEmailWithoutCartIsLeftAlone(): void
    {
        $vars = ['{firstname}' => 'Jan'];
        $params = ['template' => 'order_conf', 'templateVars' => &$vars];

        self::assertTrue($this->createModule()->hookActionEmailSendBefore($params));
        self::assertSame(['{firstname}' => 'Jan'], $vars);
    }

    /**
     * @dataProvider foreignOrderProvider
     */
    public function testEmailForOtherOrdersIsLeftAlone(?string $module): void
    {
        Stubs::returns('Order::getIdByCartId', 12);
        if ($module !== null) {
            Stubs::object('Order', 12, ['module' => $module]);
        }
        $vars = [];
        $params = ['template' => 'order_conf', 'templateVars' => &$vars, 'cart' => $this->cartRef(9)];

        self::assertTrue($this->createModule()->hookActionEmailSendBefore($params));
        self::assertSame([], $vars);
    }

    public function foreignOrderProvider(): array
    {
        return ['no order yet' => [null], 'other payment module' => ['ps_checkpayment']];
    }

    public function testOrderConfirmationEmailGetsTheBuckarooFee(): void
    {
        $this->emailOrder();
        $context = $this->contextCart(1, 0.0);
        $context->stub('getAverageProductsTaxRate', 0.21);
        $fees = new ServiceDouble(['getPaymentMethodByLabel' => 'ideal', 'getBuckarooFeeValue' => '2']);
        $vars = ['{total_paid}' => 'old'];
        $templatePath = _PS_MAIL_DIR_;
        $params = [
            'template' => 'order_conf',
            'templateVars' => &$vars,
            'templatePath' => &$templatePath,
            'cart' => $this->cartRef(9),
        ];
        \Configuration::updateValue('BUCKAROO_PAYMENT_FEE_FRONTEND_LABEL', 'Payment Fee');

        $result = $this->createModule(['buckaroo.config.api.fee.service' => $fees])->hookActionEmailSendBefore($params);

        self::assertTrue($result);
        self::assertSame(_PS_MODULE_DIR_ . 'buckaroo3/mails/', $templatePath);
        self::assertSame([['iDEAL']], $fees->callsTo('getPaymentMethodByLabel'));
        self::assertSame([
            '{total_paid}' => 'USD 102.42',
            '{payment_fee_label}' => 'Payment Fee',
            '{payment_fee}' => 'USD 2.00',
            '{payment_fee_tax}' => 'USD 0.42',
            '{total_tax_paid}' => 'USD 17.78',
        ], $vars);
    }

    public function testOrderConfirmationEmailWithoutFee(): void
    {
        $this->emailOrder();
        $fees = new ServiceDouble(['getPaymentMethodByLabel' => 'ideal', 'getBuckarooFeeValue' => null]);
        $vars = [];
        $params = ['template' => 'order_conf', 'templateVars' => &$vars, 'cart' => $this->cartRef(9)];

        $this->createModule(['buckaroo.config.api.fee.service' => $fees])->hookActionEmailSendBefore($params);

        self::assertSame(['{payment_fee_label}' => false, '{payment_fee}' => 'USD 0.00'], $vars);
    }

    public function testOtherEmailsOfBuckarooOrdersAreNotChanged(): void
    {
        $this->emailOrder();
        $fees = new ServiceDouble();
        $vars = [];
        $params = ['template' => 'shipped', 'templateVars' => &$vars, 'cart' => $this->cartRef(9)];

        self::assertTrue($this->createModule(['buckaroo.config.api.fee.service' => $fees])->hookActionEmailSendBefore($params));
        self::assertSame([], $vars);
        self::assertSame([], $fees->calls);
    }

    public function testUnpaidOrderConfirmationIsHeldWhenDeferred(): void
    {
        $this->emailOrder();
        \Configuration::updateValue('BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL', 1);
        $fees = new ServiceDouble(['getPaymentMethodByLabel' => 'ideal']);
        $vars = ['{id_order}' => 12];
        $params = ['template' => 'order_conf', 'templateVars' => &$vars, 'cart' => $this->cartRef(9)];

        $result = $this->createModule(['buckaroo.config.api.fee.service' => $fees])->hookActionEmailSendBefore($params);

        self::assertFalse($result, 'the e-mail is held until the payment is confirmed');
        self::assertStringContainsString('ps_bk_deferred_order_mail', Stubs::sql('insert')[0]);
    }

    public function testPaidStatusReleasesTheHeldConfirmation(): void
    {
        \Configuration::updateValue('BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL', 1);
        Stubs::object('Order', 12, ['module' => 'buckaroo3']);
        Stubs::db('/bk_deferred_order_mail/', [
            'id_lang' => 1,
            'template' => 'order_conf',
            'subject' => 'Order confirmation',
            'template_vars' => '{"{id_order}":12}',
            'recipient' => 'buyer@example.com',
            'recipient_name' => 'Jan',
            'template_path' => '',
            'id_shop' => 1,
        ]);
        Stubs::returns('Mail::Send', true);
        $status = new \OrderState();
        $status->paid = 1;

        $this->createModule()->hookActionOrderStatusPostUpdate(['id_order' => 12, 'newOrderStatus' => $status]);

        $sent = Stubs::callsTo('Mail::Send');
        self::assertCount(1, $sent);
        self::assertSame('order_conf', $sent[0][1]);
        self::assertSame('buyer@example.com', $sent[0][4]);
    }

    // ----- helpers ---------------------------------------------------------------

    private function order(int $id): \Order
    {
        $order = new \Order();
        $order->id = $id;
        $order->id_cart = 8;

        return $order;
    }

    private function paidOrder(int $id, float $total): void
    {
        Stubs::object('Order', $id, ['reference' => 'REF' . $id]);
        Stubs::returns('Order->getOrdersTotalPaid', $total);
    }

    private function emailOrder(): void
    {
        Stubs::returns('Order::getIdByCartId', 12);
        Stubs::object('Order', 12, [
            'module' => 'buckaroo3',
            'payment' => 'iDEAL',
            'id_currency' => 2,
            'total_paid' => 100.0,
            'total_paid_tax_incl' => 100.0,
            'total_paid_tax_excl' => 82.64,
        ]);
        Stubs::object('Cart', 9, []);
        Stubs::returns('Cart->getOrderTotal', 100.0);
        \Currency::$isoById[2] = 'USD';
    }

    private function cartRef(int $id): \stdClass
    {
        $cart = new \stdClass();
        $cart->id = $id;

        return $cart;
    }

    private function taxRate(float $rate): void
    {
        $calculator = new ServiceDouble(['getTotalRate' => $rate]);
        Stubs::returns('TaxManagerFactory::getManager', new ServiceDouble(['getTaxCalculator' => $calculator]));
    }
}
