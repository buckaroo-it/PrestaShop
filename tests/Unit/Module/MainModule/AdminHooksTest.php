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
use PrestaShop\PrestaShop\Core\Product\ProductExtraContent;
use PrestaShopBundle\Service\Routing\Router;
use Symfony\Component\HttpFoundation\Session\Session;

require_once __DIR__ . '/MainModuleTestCase.php';

/**
 * Back office hooks: admin order page, back office header, PDF invoice and the
 * product form (iDIN) hooks.
 */
class AdminHooksTest extends MainModuleTestCase
{
    public function testAdminOrderBlockIsOnlyForBuckarooOrders(): void
    {
        Stubs::object('Order', 5, ['module' => 'ps_wirepayment']);
        $refunds = new ServiceDouble();
        $module = $this->createModule(['buckaroo.refund.admin.provider' => $refunds]);

        self::assertNull($module->hookDisplayAdminOrderMainBottom(['id_order' => 5]));
        self::assertSame([], $refunds->calls);
    }

    public function testAdminOrderBlockShowsFeeRefundsAndKlarnaReservation(): void
    {
        Stubs::object('Order', 5, ['module' => 'buckaroo3', 'id_currency' => 2]);
        \Currency::$isoById[2] = 'USD';
        Stubs::db('/bk_buckaroo_fee WHERE id_order = 5/', [
            'buckaroo_fee_tax_excl' => 2.0,
            'buckaroo_fee_tax_incl' => 2.42,
            'fee_refunded' => 1,
        ]);
        $session = new Session();
        $session->set('buckaroo_include_fee_5', true);
        $refunds = new ServiceDouble(['get' => ['buckaroo_refunds' => ['r1']]]);
        $klarna = new ServiceDouble(['getAdminData' => ['key' => 'RES-1', 'cancelSent' => true]]);
        $module = $this->createModule([
            'buckaroo.refund.admin.provider' => $refunds,
            'buckaroo.session' => $session,
            'prestashop.router' => new Router(),
            'buckaroo.klarna.reservation' => $klarna,
        ]);

        $html = $module->hookDisplayAdminOrderMainBottom(['id_order' => 5]);

        self::assertSame(
            'display:views/templates/hook/payment-fee-table.tpl'
            . 'display:views/templates/hook/klarna-reservation.tpl'
            . 'display:views/templates/hook/refund-hook.tpl',
            $html
        );
        $vars = $this->context()->smarty->getTemplateVars();
        self::assertSame(['r1'], $vars['buckaroo_refunds']);
        self::assertEqualsWithDelta(0.42, $vars['buckaroo_fee']['buckaroo_fee_tax'], 0.0001);
        self::assertSame(['tax_excl' => 'USD 2.00', 'tax' => 'USD 0.42', 'tax_incl' => 'USD 2.42'], $vars['buckaroo_fee_display']);
        self::assertSame('USD', $vars['currency']->iso_code);
        self::assertTrue($vars['buckaroo_fee_refunded']);
        self::assertTrue($vars['buckaroo_fee_flag_set']);
        self::assertSame('/admin/buckaroo_set_refund_fee_flag', $vars['buckaroo_set_fee_flag_url']);
        self::assertSame('RES-1', $vars['klarnaReservationKey']);
        self::assertTrue($vars['klarnaCancelSent']);
        self::assertSame(5, $vars['klarnaOrderId']);
        self::assertSame('/admin/buckaroo_klarna_cancel_reservation', $vars['klarnaCancelUrl']);
        self::assertSame('/admin/buckaroo_klarna_extend_reservation', $vars['klarnaExtendUrl']);
        self::assertSame(5, $refunds->callsTo('get')[0][0]->id);
    }

    public function testAdminOrderBlockWithoutFeeOrReservation(): void
    {
        Stubs::object('Order', 6, ['module' => 'buckaroo3']);
        $module = $this->createModule([
            'buckaroo.refund.admin.provider' => new ServiceDouble(['get' => []]),
            'buckaroo.session' => new Session(),
            'prestashop.router' => new Router(),
            'buckaroo.klarna.reservation' => new ServiceDouble(['getAdminData' => null]),
        ]);

        $html = $module->hookDisplayAdminOrderMainBottom(['id_order' => 6]);

        self::assertSame(
            'display:views/templates/hook/payment-fee-table.tpldisplay:views/templates/hook/refund-hook.tpl',
            $html
        );
        $vars = $this->context()->smarty->getTemplateVars();
        self::assertSame(['buckaroo_fee_tax_excl' => 0, 'buckaroo_fee_tax_incl' => 0, 'buckaroo_fee_tax' => 0], $vars['buckaroo_fee']);
        self::assertSame('EUR 0.00', $vars['buckaroo_fee_display']['tax_incl']);
        self::assertFalse($vars['buckaroo_fee_refunded']);
        self::assertFalse($vars['buckaroo_fee_flag_set']);
        self::assertArrayNotHasKey('klarnaReservationKey', $vars);
    }

    /**
     * @dataProvider backOfficeHeaderProvider
     */
    public function testBackOfficeHeaderStyles(array $query, array $expectedCss): void
    {
        $controller = $this->frontController('AdminModules');
        $this->request($query);

        $this->createModule()->hookDisplayBackOfficeHeader();

        self::assertSame($expectedCss, $this->addedCss($controller));
    }

    public function backOfficeHeaderProvider(): array
    {
        return [
            'module configuration page' => [
                ['controller' => 'AdminModules', 'configure' => 'buckaroo3'],
                ['/modules/buckaroo3/views/css/buckaroo3.vue.css', '/modules/buckaroo3/views/css/buckaroo3.admin.css'],
            ],
            'other module configuration' => [
                ['controller' => 'AdminModules', 'configure' => 'ps_banner'],
                ['/modules/buckaroo3/views/css/buckaroo3.admin.css'],
            ],
            'orders page' => [
                ['controller' => 'AdminOrders'],
                ['/modules/buckaroo3/views/css/buckaroo3.admin.css'],
            ],
        ];
    }

    public function testPdfInvoiceShowsTheFeeWithTax(): void
    {
        $order = new \Order();
        $order->id = 9;
        $order->id_address_invoice = 3;
        $invoice = new \OrderInvoice();
        $invoice->stub('getOrder', $order);
        Stubs::db('/bk_buckaroo_fee WHERE id_order = 9/', ['buckaroo_fee_tax_excl' => '10', 'buckaroo_fee_tax_incl' => '12.1']);
        $this->taxRate(21.0);
        Stubs::object('Address', 3, ['city' => 'Utrecht']);
        \Configuration::updateValue('BUCKAROO_PAYMENT_FEE_FRONTEND_LABEL', 'Payment Fee');

        $html = $this->createModule()->hookDisplayPDFInvoice(['object' => $invoice]);

        self::assertSame('rendered:invoice_fee.tpl', $html);
        self::assertStringEndsWith('buckaroo3/views/templates/admin/invoice_fee.tpl', Stubs::callsTo('Smarty->fetch')[0][0]);
        $vars = $this->context()->smarty->getTemplateVars();
        self::assertSame('Payment Fee', $vars['payment_fee_label']);
        self::assertSame('EUR 12.10', $vars['order_buckaroo_fee']);
        self::assertSame('EUR 2.10', $vars['order_buckaroo_fee_tax']);
        self::assertSame(3, Stubs::callsTo('TaxManagerFactory::getManager')[0][0]->id);
    }

    public function testPdfInvoiceWithoutFee(): void
    {
        $order = new \Order();
        $order->id = 9;
        $invoice = new \OrderInvoice();
        $invoice->stub('getOrder', $order);

        self::assertNull($this->createModule()->hookDisplayPDFInvoice(['object' => $invoice]));
        self::assertSame([], Stubs::callsTo('Smarty->fetch'));
    }

    public function testPdfInvoiceIgnoresOtherDocuments(): void
    {
        self::assertNull($this->createModule()->hookDisplayPDFInvoice(['object' => new \OrderSlip()]));
        self::assertSame([], Stubs::sql());
    }

    public function testProductFormBuilderIsModified(): void
    {
        $modifier = new ServiceDouble();
        $builder = new \stdClass();
        $module = $this->createModule([
            'Buckaroo\PrestaShop\Src\Form\Modifier\ProductFormModifier' => $modifier,
        ]);

        $module->hookActionProductFormBuilderModifier(['id' => '12', 'form_builder' => $builder]);

        self::assertSame([[12, $builder]], $modifier->callsTo('modify'));
    }

    /**
     * @dataProvider productIdinSaveProvider
     */
    public function testProductIdinSettingIsSaved($exists, string $expectedSql): void
    {
        Stubs::db('/COUNT\(\*\).*bk_product_idin/s', $exists);

        $this->createModule()->hookActionAfterUpdateProductFormHandler([
            'form_data' => ['id' => 12, 'buckaroo_idin' => ['buckaroo_idin' => '1']],
        ]);

        self::assertSame([$expectedSql], Stubs::sql('execute'));
    }

    public function productIdinSaveProvider(): array
    {
        return [
            'existing row' => ['1', 'UPDATE ps_bk_product_idin SET buckaroo_idin = 1 WHERE product_id = 12'],
            'new row' => ['0', 'INSERT INTO ps_bk_product_idin (product_id, buckaroo_idin) VALUES (12, 1)'],
        ];
    }

    public function testProductIdinSaveErrorsAreLogged(): void
    {
        Stubs::db('/COUNT/', '0');
        Stubs::db('/INSERT INTO/', function () {
            throw new \RuntimeException('table missing');
        });
        $module = $this->createModule();

        $module->hookActionAfterUpdateProductFormHandler([
            'form_data' => ['id' => 12, 'buckaroo_idin' => ['buckaroo_idin' => 0]],
        ]);

        $logged = $module->logger->callsTo('logError');
        self::assertCount(1, $logged);
        self::assertSame(
            'Buckaroo3::updateProductFormHandler - Error while executing SQL query: table missing',
            $logged[0][0]
        );
    }

    /**
     * @dataProvider idinProductBoxProvider
     */
    public function testIdinProductBox(array $idinConfig, $productFlag, bool $expected): void
    {
        Stubs::db('/bk_product_idin/', $productFlag);
        $module = $this->createModule([
            'buckaroo.config.api.config.service' => $this->configService(['idin' => $idinConfig]),
        ]);
        $product = new \Product();
        $product->id = 4;

        self::assertSame($expected, $module->isIdinProductBoxShow(['product' => $product]));
    }

    public function idinProductBoxProvider(): array
    {
        return [
            'idin off' => [['mode' => 'off', 'display_mode' => 'global'], '1', false],
            'global' => [['mode' => 'live', 'display_mode' => 'global'], false, true],
            'product enabled' => [['mode' => 'test', 'display_mode' => 'product'], '1', true],
            'product disabled' => [['mode' => 'test', 'display_mode' => 'product'], '0', false],
            'unknown display mode' => [['mode' => 'test', 'display_mode' => 'none'], '1', false],
        ];
    }

    public function testIdinProductBoxQueriesTheProductFlag(): void
    {
        Stubs::db('/bk_product_idin/', '1');
        $module = $this->createModule([
            'buckaroo.config.api.config.service' => $this->configService(['idin' => ['mode' => 'live', 'display_mode' => 'product']]),
        ]);
        $product = new \Product();
        $product->id = 4;

        $module->isIdinProductBoxShow(['product' => $product]);

        $sql = Stubs::sql('getValue')[0];
        self::assertStringContainsString('SELECT buckaroo_idin', $sql);
        self::assertStringContainsString('product_id = 4', $sql);
    }

    public function testProductExtraContentShowsTheIdinBox(): void
    {
        $module = $this->createModule([
            'buckaroo.config.api.config.service' => $this->configService(['idin' => ['mode' => 'live', 'display_mode' => 'global']]),
        ]);
        $module->smarty = $this->context()->smarty;

        $content = $module->hookDisplayProductExtraContent(['product' => new \Product()]);

        self::assertCount(1, $content);
        self::assertInstanceOf(ProductExtraContent::class, $content[0]);
        self::assertSame('iDIN Info', $content[0]->getTitle());
        self::assertSame('display:views/templates/hook/idin_box.tpl', $content[0]->getContent());
        self::assertSame('/modules/buckaroo3/', $this->context()->smarty->getTemplateVars('this_path'));
    }

    public function testProductExtraContentIsEmptyWithoutIdin(): void
    {
        $module = $this->createModule([
            'buckaroo.config.api.config.service' => $this->configService(['idin' => ['mode' => 'off']]),
        ]);

        self::assertNull($module->hookDisplayProductExtraContent(['product' => new \Product()]));
    }

    /**
     * @dataProvider idinCheckoutProvider
     */
    public function testIdinCheckout(array $idinConfig, array $enabledProducts, bool $expected): void
    {
        Stubs::db('/bk_product_idin/', function ($sql) use ($enabledProducts) {
            foreach ($enabledProducts as $id) {
                if (strpos($sql, 'product_id = ' . $id) !== false) {
                    return '1';
                }
            }

            return '0';
        });
        $cart = new \Cart();
        $cart->stub('getProducts', [['id_product' => 1], ['id_product' => 2]]);
        $module = $this->createModule([
            'buckaroo.config.api.config.service' => $this->configService(['idin' => $idinConfig]),
        ]);

        self::assertSame($expected, $module->isIdinCheckout($cart));
    }

    public function idinCheckoutProvider(): array
    {
        return [
            'idin off' => [['mode' => 'off', 'display_mode' => 'global'], [], false],
            'global' => [['mode' => 'live', 'display_mode' => 'global'], [], true],
            'a product requires it' => [['mode' => 'live', 'display_mode' => 'product'], [2], true],
            'no product requires it' => [['mode' => 'live', 'display_mode' => 'product'], [], false],
            'unknown display mode' => [['mode' => 'live', 'display_mode' => 'x'], [1, 2], false],
        ];
    }

    private function taxRate(float $rate): void
    {
        $calculator = new ServiceDouble(['getTotalRate' => $rate]);
        $manager = new ServiceDouble(['getTaxCalculator' => $calculator]);
        Stubs::returns('TaxManagerFactory::getManager', $manager);
    }
}
