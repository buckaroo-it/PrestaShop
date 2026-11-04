<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\PrestaShop\Src\Refund\OrderMessage;
use Buckaroo\PrestaShop\Src\Refund\Payment\OrderPayment;
use Buckaroo\PrestaShop\Src\Refund\Payment\Service;
use Buckaroo\PrestaShop\Src\Refund\Settings;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/StubsSupport.php';

class RefundSettingsAndPaymentsTest extends TestCase
{
    private const KEYS = [
        Settings::LABEL_REFUND_CONF,
        Settings::LABEL_REFUND_RESTOCK,
        Settings::LABEL_REFUND_CREDIT_SLIP,
        Settings::LABEL_REFUND_VOUCHER,
        Settings::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT,
    ];

    public function testFormFieldsListEveryRefundSettingAndTranslateLabels(): void
    {
        $module = new class {
            public function l($string)
            {
                return 'T:' . $string;
            }
        };

        $form = (new Settings())->getFormFields($module);

        self::assertSame('T:Refund settings', $form['legend']);
        self::assertSame('REFUND', $form['name']);
        self::assertTrue($form['enabled']);
        $names = array_column($form['input'], 'name');
        self::assertSame(array_merge(self::KEYS, ['save_data']), $names);
        self::assertSame('T:Create negative payments on refund', $form['input'][4]['label']);
        self::assertSame('submit', $form['input'][5]['type']);
    }

    public function testFormFieldsNeedATranslator(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot find module translator');

        (new Settings())->getFormFields(new \stdClass());
    }

    public function testInstallSetsDefaultsWithCreditSlipEnabled(): void
    {
        (new Settings())->install();

        self::assertSame([
            Settings::LABEL_REFUND_CONF => false,
            Settings::LABEL_REFUND_RESTOCK => false,
            Settings::LABEL_REFUND_CREDIT_SLIP => true,
            Settings::LABEL_REFUND_VOUCHER => false,
            Settings::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT => false,
        ], (new Settings())->getValues());
    }

    public function testUpdateAllStoresSubmittedValuesAndUninstallRemovesThem(): void
    {
        $settings = new Settings();
        $this->request([], [
            Settings::LABEL_REFUND_CONF => '1',
            Settings::LABEL_REFUND_RESTOCK => '0',
            Settings::LABEL_REFUND_CREDIT_SLIP => '1',
            Settings::LABEL_REFUND_VOUCHER => '0',
            Settings::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT => '1',
        ]);

        $settings->updateAll();

        self::assertSame(['1', '0', '1', '0', '1'], array_values($settings->getValues()));

        $settings->uninstall();
        foreach (self::KEYS as $key) {
            self::assertFalse(\Configuration::hasKey($key), $key);
        }
    }

    public function testOrderMessageIsAddedToTheOrder(): void
    {
        $order = new \Order();
        $order->id = 12;

        (new OrderMessage())->add($order, 'Refunded 10 EUR');

        $messages = Stubs::persisted('Message', 'add');
        self::assertCount(1, $messages);
        self::assertSame(12, $messages[0]->id_order);
        self::assertSame('Refunded 10 EUR', $messages[0]->message);
    }

    public function testPaymentServiceSavesANegativeOrderPayment(): void
    {
        $order = new \Order();
        $order->reference = 'REF123';
        $order->id_currency = 3;

        (new Service())->create($order, 'REFUNDKEY', 'ideal', -12.5);

        $saved = Stubs::persisted(OrderPayment::class, 'save');
        self::assertCount(1, $saved);
        self::assertInstanceOf(\OrderPayment::class, $saved[0]);
        self::assertSame('REF123', $saved[0]->order_reference);
        self::assertSame(3, $saved[0]->id_currency);
        self::assertSame('REFUNDKEY', $saved[0]->transaction_id);
        self::assertSame(-12.5, $saved[0]->amount);
        self::assertSame('ideal', $saved[0]->payment_method);
    }

    public function testOrderPaymentAllowsNegativeAmounts(): void
    {
        $original = \ObjectModel::$definition;
        \ObjectModel::$definition = ['table' => 'order_payment', 'fields' => ['amount' => ['validate' => 'isNegativePrice']]];

        try {
            new OrderPayment();

            self::assertSame('isAnything', \ObjectModel::$definition['fields']['amount']['validate']);
        } finally {
            \ObjectModel::$definition = $original;
        }
    }

    public function testOrderPaymentWithoutAmountValidationIsLeftAlone(): void
    {
        $original = \ObjectModel::$definition;

        $payment = new OrderPayment();

        self::assertSame($original, \ObjectModel::$definition);
        self::assertNull($payment->id);
    }
}
