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

use Buckaroo\BuckarooClient;
use BuckarooTest\Stubs;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

require_once __DIR__ . '/MainModuleTestCase.php';

/**
 * hookPaymentOptions() with verified credentials.
 *
 * The module verifies the Buckaroo credentials with a live request. Each test
 * runs in its own process and swaps in an offline BuckarooClient
 * (Fixtures/OfflineBuckarooClient.php) so nothing reaches the network.
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class PaymentOptionsTest extends MainModuleTestCase
{
    /** @var ServiceDouble */
    private $paymentService;

    /** @var PaymentOption */
    private $option;

    protected function setUp(): void
    {
        parent::setUp();
        // PayByBank sets an undeclared property (deprecated on PHP 8.2+); in a child
        // process the printed notice would count as unexpected output.
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');
        require_once __DIR__ . '/Fixtures/OfflineBuckarooClient.php';
        BuckarooClient::$credentialsValid = true;
        BuckarooClient::$created = [];

        \Configuration::updateValue('BUCKAROO_MERCHANT_KEY', 'website');
        \Configuration::updateValue('BUCKAROO_SECRET_KEY', 'secret');
        \Configuration::updateValue('PS_LANG_DEFAULT', 2);
        // Pay by bank issuers are cached for today, so no issuer request is made.
        \Configuration::updateValue('BUCKAROO_PAYBYBANK_ISSUERS_CACHE', json_encode(['BUNQNL2A' => ['name' => 'bunq', 'logo' => 'Bunq.svg']]));
        \Configuration::updateValue('BUCKAROO_PAYBYBANK_ISSUERS_CACHE_DATE', (new \DateTime())->format('Y-m-d'));
        \Country::$isoById[13] = 'NL';
        Stubs::returns('Tools::getCountry', 13);

        $this->context()->cookie->id_cart = 8;
        Stubs::returns('Cart->getOrderTotal', 121.0);
        Stubs::object('Customer', 3, [
            'firstname' => 'Jan',
            'lastname' => 'Jansen',
            'email' => 'jan@example.com',
            'birthday' => '1990-05-17',
            'id_gender' => 1,
        ]);

        $this->option = (new PaymentOption())->setModuleName('buckaroo3')->setCallToActionText('iDEAL');
        $this->paymentService = new ServiceDouble([
            'showAfterpayCoc' => true,
            'showBillinkCoc' => false,
            'areHouseNumberValidForCountryDE' => true,
            'getPaymentOptions' => [$this->option],
        ]);
    }

    protected function tearDown(): void
    {
        // The child process serializes the test case; closures cannot be serialized.
        $this->services = [];
        $this->paymentService = null;
        parent::tearDown();
    }

    private function module(array $overrides = []): \Buckaroo3
    {
        return $this->createModule($overrides + [
            'buckaroo.config.api.payment.service' => $this->paymentService,
            'buckaroo.config.api.config.service' => $this->configService(
                [
                    'paybybank' => ['display_type' => 'dropdown'],
                    'creditcard' => ['display_type' => 'radio'],
                    'giftcard' => ['display_in_checkout' => 'list'],
                    'idin' => ['mode' => 'test'],
                ],
                ['getActiveCreditCards' => [['service_code' => 'visa']]]
            ),
            'buckaroo.classes.issuers.capayableIn3' => new ServiceDouble(['getMethod' => 'In3']),
            'buckaroo.config.api.clicktopay.service' => new ServiceDouble(['isConfigured' => true]),
            'buckaroo.config.api.fee.service' => new ServiceDouble(['getBuckarooFees' => []]),
        ]);
    }

    /**
     * @dataProvider addressProvider
     */
    public function testPaymentOptionsWithCustomerDetails(array $cart, array $addresses, array $expected): void
    {
        Stubs::object('Cart', 8, $cart + ['id_customer' => 3]);
        Stubs::returns('Customer->getAddresses', $addresses);
        $controller = $this->frontController('order');

        $options = $this->module()->hookPaymentOptions(['cookie' => $this->context()->cookie]);

        self::assertSame([$this->option], $options);
        self::assertSame([['website', 'secret']], BuckarooClient::$created);
        self::assertSame(8, $this->paymentService->callsTo('getPaymentOptions')[0][0]->id);
        self::assertSame([2], Stubs::callsTo('Customer->getAddresses')[0]);

        $vars = $this->context()->smarty->getTemplateVars();
        foreach ($expected as $key => $value) {
            self::assertSame($value, $vars[$key], $key);
        }
        self::assertSame('/modules/buckaroo3/', $vars['this_path']);
        self::assertSame(1, $vars['customer_gender']);
        self::assertSame('Jan Jansen', $vars['customer_name']);
        self::assertSame('jan@example.com', $vars['customer_email']);
        self::assertSame(['1990', '05', '17'], $vars['customer_birthday']);
        self::assertSame(121.0, $vars['total']);
        self::assertSame('NL', $vars['country']);
        self::assertTrue($vars['afterpay_show_coc']);
        self::assertFalse($vars['billink_show_coc']);
        self::assertSame(['name' => 'bunq', 'logo' => 'Bunq.svg', 'selected' => false], $vars['paybybankIssuers']['BUNQNL2A']);
        self::assertArrayHasKey('INGBNL2A', $vars['paybybankIssuers']);
        self::assertSame('dropdown', $vars['payByBankDisplayMode']);
        self::assertSame([['service_code' => 'visa']], $vars['creditcardIssuers']);
        self::assertSame('radio', $vars['creditCardDisplayMode']);
        self::assertSame('list', $vars['giftCardDisplayMode']);
        self::assertSame('In3', $vars['in3Method']);
        self::assertSame('test', $vars['buckaroo_idin_test']);
        self::assertTrue($vars['houseNumbersAreValid']);
        self::assertTrue($vars['clickToPayConfigured']);

        self::assertArrayHasKey('module-buckaroo3', $this->registeredJs($controller));
    }

    public function addressProvider(): array
    {
        $delivery = [
            'id_address' => 11, 'firstname' => 'Piet', 'lastname' => 'Jansen', 'company' => '',
            'vat_number' => '', 'phone' => '0101234567', 'phone_mobile' => '',
        ];
        $invoice = [
            'id_address' => 12, 'firstname' => 'Jan', 'lastname' => 'Jansen', 'company' => 'ACME',
            'vat_number' => 'NL123B01', 'phone' => '0201234567', 'phone_mobile' => '0612345678',
        ];
        $other = ['id_address' => 99] + $delivery;

        return [
            'different person at the delivery address' => [
                ['id_address_delivery' => 11, 'id_address_invoice' => 12],
                [$other, $delivery, $invoice],
                [
                    'address_differ' => 1,
                    'customer_company' => 'ACME',
                    'customer_vat' => 'NL123B01',
                    'phone' => '0101234567',
                    'phone_mobile' => '',
                    'phone_afterpay_shipping' => '0101234567',
                    'phone_afterpay_billing' => '0612345678',
                ],
            ],
            'same person at another address' => [
                ['id_address_delivery' => 11, 'id_address_invoice' => 12],
                [['firstname' => 'Jan'] + $delivery, ['phone_mobile' => ''] + $invoice],
                [
                    'address_differ' => 2,
                    'phone_afterpay_shipping' => '0101234567',
                    'phone_afterpay_billing' => '0201234567',
                ],
            ],
            'one address' => [
                ['id_address_delivery' => 12, 'id_address_invoice' => 12],
                [$invoice],
                [
                    'address_differ' => 0,
                    'phone' => '0201234567',
                    'phone_mobile' => '0612345678',
                    'phone_afterpay_shipping' => '0612345678',
                    'phone_afterpay_billing' => '0612345678',
                ],
            ],
            'no phone numbers' => [
                ['id_address_delivery' => 12, 'id_address_invoice' => 12],
                [['phone' => '', 'phone_mobile' => ''] + $invoice],
                ['phone_afterpay_shipping' => '', 'phone_afterpay_billing' => '', 'customer_company' => 'ACME'],
            ],
        ];
    }

    public function testCheckoutDataErrorsAreLoggedAndOptionsStillReturned(): void
    {
        Stubs::object('Cart', 8, ['id_customer' => 3]);
        Stubs::returns('Customer->getAddresses', []);
        $this->frontController('order');
        $module = $this->module([
            'buckaroo.classes.issuers.capayableIn3' => new ServiceDouble(['getMethod' => function () {
                throw new \RuntimeException('in3 config missing');
            }]),
        ]);

        self::assertSame([$this->option], $module->hookPaymentOptions(['cookie' => $this->context()->cookie]));
        self::assertSame(
            ['Buckaroo3::hookPaymentOptions - in3 config missing'],
            array_column($module->logger->callsTo('logError'), 0)
        );
        self::assertNull($this->context()->smarty->getTemplateVars('address_differ'));
    }

    public function testNoPaymentOptionsWithRejectedCredentials(): void
    {
        BuckarooClient::$credentialsValid = false;

        self::assertSame([], $this->module()->hookPaymentOptions(['cookie' => $this->context()->cookie]));
        self::assertSame([['website', 'secret']], BuckarooClient::$created);
        self::assertSame([], $this->paymentService->calls);
    }
}
