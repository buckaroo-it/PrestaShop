<?php

declare(strict_types=1);

namespace BuckarooTest\Unit\Services;

use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReportingConsent;
use Buckaroo\PrestaShop\Src\Service\BuckarooFeeService;
use Buckaroo\PrestaShop\Src\Service\BuckarooIdinService;
use Buckaroo\PrestaShop\Src\Service\BuckarooSettingsService;
use Buckaroo\PrestaShop\Src\Service\PaymentLabelResolver;
use Buckaroo\PrestaShop\Src\Service\PriceFormatter;
use Buckaroo\PrestaShop\Src\Service\ReturnStateSigner;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use Doctrine\ORM\EntityManager;

final class ServiceTopUpTest extends TestCase
{
    // ---- BuckarooIdinService ------------------------------------------------

    public function testIdinExistenceChecksCountRowsOfTheCustomerAndProduct(): void
    {
        Stubs::db('/bk_customer_idin/', '1');
        Stubs::db('/bk_product_idin/', '0');
        $service = new BuckarooIdinService();

        self::assertSame('1', $service->checkCustomerIdExists(5));
        self::assertSame('0', $service->checkProductIdExists(9));
        self::assertSame([
            'SELECT COUNT(*) FROM `ps_bk_customer_idin` WHERE (customer_id = 5)',
            'SELECT COUNT(*) FROM `ps_bk_product_idin` WHERE (product_id = 9)',
        ], Stubs::sql('getValue'));
    }

    public function testIdinWritesEscapeTheirValues(): void
    {
        $service = new BuckarooIdinService();

        self::assertTrue($service->updateCustomerData('5', 'bin"1', 'True'));
        self::assertTrue($service->insertCustomerData('6x', 'bin', 'False'));
        self::assertTrue($service->updateProductData('9', '1'));
        self::assertTrue($service->insertProductData(10, 'yes'));

        self::assertSame([
            'UPDATE ps_bk_customer_idin SET buckaroo_idin_consumerbin="bin\\"1", buckaroo_idin_iseighteenorolder="True" WHERE customer_id=5',
            'INSERT INTO ps_bk_customer_idin (customer_id, buckaroo_idin_consumerbin, buckaroo_idin_iseighteenorolder) VALUES (6, "bin", "False")',
            'UPDATE ps_bk_product_idin SET buckaroo_idin = 1 WHERE product_id = 9',
            'INSERT INTO ps_bk_product_idin (product_id, buckaroo_idin) VALUES (10, 0)',
        ], Stubs::sql('execute'));
    }

    public function testIdinWriteFailureIsWrapped(): void
    {
        Stubs::db('/bk_product_idin/', function () {
            throw new \RuntimeException('table missing');
        });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error while executing SQL query: table missing');

        (new BuckarooIdinService())->insertProductData(1, 1);
    }

    // ---- BuckarooSettingsService --------------------------------------------

    private static function settingsData(array $overrides = []): array
    {
        return array_merge([
            'website_key' => 'wk',
            'secret_key' => 'sk',
            'transaction_description' => 'Order {order_number}',
            'refundconf' => true,
            'restock' => false,
            'creditSlip' => true,
            'voucher' => false,
            'negativePayment' => true,
            'payment_fee_mode' => 'subtotal',
            'payment_fee_frontend_label' => 'Fee',
            'disable_unpaid_order_email' => true,
        ], $overrides);
    }

    public function testSettingsRoundTrip(): void
    {
        $service = new BuckarooSettingsService();
        $service->updateSettings(self::settingsData(['error_reporting' => 'true']));

        self::assertSame([
            'is_enabled' => 1,
            'website_key' => 'wk',
            'secret_key' => 'sk',
            'transaction_description' => 'Order {order_number}',
            'refundconf' => true,
            'restock' => false,
            'creditSlip' => true,
            'voucher' => false,
            'negativePayment' => true,
            'payment_fee_mode' => 'subtotal',
            'payment_fee_frontend_label' => 'Fee',
            'error_reporting' => true,
            'error_reporting_decided' => true,
            'disable_unpaid_order_email' => true,
        ], $service->getSettings());
        self::assertSame(1, \Configuration::get(Config::BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL));
    }

    public function testSettingsKeepTheErrorReportingDecisionWhenNotSent(): void
    {
        $service = new BuckarooSettingsService();

        $service->updateSettings(self::settingsData(['disable_unpaid_order_email' => false]));
        self::assertFalse(\Configuration::hasKey(ErrorReportingConsent::CONFIG_KEY));
        self::assertFalse($service->getSettings()['error_reporting_decided']);
        self::assertSame(0, \Configuration::get(Config::BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL));
        self::assertFalse($service->getSettings()['disable_unpaid_order_email']);

        $service->updateSettings(self::settingsData(['error_reporting' => null]));
        self::assertFalse(\Configuration::hasKey(ErrorReportingConsent::CONFIG_KEY), 'null means "not decided"');

        $service->updateSettings(self::settingsData(['error_reporting' => 'false']));
        self::assertSame(ErrorReportingConsent::NO, \Configuration::get(ErrorReportingConsent::CONFIG_KEY));
        self::assertFalse($service->getSettings()['error_reporting']);
        self::assertTrue($service->getSettings()['error_reporting_decided']);
    }

    public function testSettingsValidationNeedsEveryKey(): void
    {
        $service = new BuckarooSettingsService();

        self::assertTrue($service->isValidData(self::settingsData()));
        foreach (array_keys(self::settingsData()) as $key) {
            $data = self::settingsData();
            unset($data[$key]);
            self::assertFalse($service->isValidData($data), $key . ' is required');
        }
        self::assertFalse($service->isValidData(self::settingsData(['secret_key' => null])));
    }

    // ---- BuckarooFeeService -------------------------------------------------

    private function feeService(array $configs): BuckarooFeeService
    {
        $methods = new class(array_keys($configs)) {
            /** @var string[] */
            private $names;

            public function __construct(array $names)
            {
                $this->names = $names;
            }

            public function findOneByName($name)
            {
                $id = array_search($name, $this->names, true);

                return $id === false ? null : new class($id) {
                    private $id;

                    public function __construct($id)
                    {
                        $this->id = $id;
                    }

                    public function getId()
                    {
                        return $this->id;
                    }
                };
            }
        };
        $configurations = new class(array_values($configs)) {
            /** @var array */
            private $configs;

            public function __construct(array $configs)
            {
                $this->configs = $configs;
            }

            public function getConfigArray($id)
            {
                return $this->configs[$id];
            }
        };

        $em = new EntityManager();
        $em->repositories = [BkPaymentMethods::class => $methods, BkConfiguration::class => $configurations];

        return new BuckarooFeeService($em, $this->context());
    }

    public function testFeeConfigOfAnUnknownMethodIsEmpty(): void
    {
        $service = $this->feeService(['ideal' => ['payment_fee' => '1']]);

        self::assertNull($service->getConfigArrayForMethod('unknown'));
        self::assertNull($service->getBuckarooFeeValue('unknown'));
        self::assertSame([['type' => 'hidden', 'name' => 'buckarooKey', 'value' => 'unknown']], $service->getBuckarooFeeInputs('unknown'));
    }

    /**
     * @dataProvider noFeeProvider
     */
    public function testZeroOrNegativeFeeAddsNoFeeInputs(string $fee): void
    {
        $service = $this->feeService(['ideal' => ['payment_fee' => $fee]]);

        self::assertSame([['type' => 'hidden', 'name' => 'buckarooKey', 'value' => 'ideal']], $service->getBuckarooFeeInputs('ideal'));
    }

    public function noFeeProvider(): array
    {
        return [['0.00'], ['-2'], ['abc']];
    }

    // ---- PaymentLabelResolver -----------------------------------------------

    private static function response(array $props): object
    {
        $response = new class {
            public $payment_method;
            public $status;

            public function isValid()
            {
                return true;
            }

            public function hasSucceeded()
            {
                return false;
            }
        };
        foreach ($props as $key => $value) {
            $response->{$key} = $value;
        }

        return $response;
    }

    public function testLabelFallsBackWhenTheRepositoryCannotLookItUp(): void
    {
        $response = self::response(['payment_method' => 'ideal', 'status' => 490]);

        self::assertSame('Buckaroo', (new PaymentLabelResolver(new \stdClass()))->resolve($response, 'Buckaroo'));
        self::assertSame('Buckaroo', (new PaymentLabelResolver(null))->resolve($response, 'Buckaroo'));
    }

    public function testLabelFallsBackWhenTheLookupFails(): void
    {
        $repository = new class {
            public function getPaymentMethodsLabel($method)
            {
                throw new \RuntimeException('db down');
            }
        };

        $response = self::response(['payment_method' => 'ideal', 'status' => 490]);

        self::assertSame('Buckaroo', (new PaymentLabelResolver($repository))->resolve($response, 'Buckaroo'));
    }

    // ---- PriceFormatter -----------------------------------------------------

    public function testPriceIsFormattedByTheContextLocale(): void
    {
        $formatter = new PriceFormatter($this->context());

        self::assertSame('EUR 12.50', $formatter->format('12.5'));
        self::assertSame('USD 3.00', $formatter->format(3, 'USD'));
    }

    public function testFormatterFallsBackToTheNeutralFormatWithoutContext(): void
    {
        $formatter = new PriceFormatter(null);

        self::assertSame('7.10 GBP', $formatter->format(7.1, 'GBP'));
        self::assertSame('7.10', $formatter->format(7.1));
    }

    public function testFormatterFallsBackWhenTheLocaleFails(): void
    {
        $context = $this->context();
        $context->currency = new \stdClass(); // no ISO code: the locale is not used

        self::assertSame('5.00', (new PriceFormatter($context))->format(5));
    }

    // ---- ReturnStateSigner --------------------------------------------------

    public function testSignerNeedsASecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A signing secret is required.');

        new ReturnStateSigner('');
    }

    /**
     * @dataProvider malformedPayloadProvider
     */
    public function testCorrectlySignedButMalformedPayloadIsRejected(string $json): void
    {
        $key = hash_hmac('sha256', 'buckaroo3-return-state', 'secret', true);
        $payload = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $key, true)), '+/', '-_'), '=');

        $signer = new ReturnStateSigner('secret', 600, function () {
            return 1000;
        });

        self::assertNull($signer->verify($payload . '.' . $signature));
    }

    public function malformedPayloadProvider(): array
    {
        return [
            'not json' => ['nope'],
            'missing outcome' => ['{"c":1,"e":2000}'],
            'cart id is a string' => ['{"c":"1","o":"success","e":2000}'],
            'expiry is a string' => ['{"c":1,"o":"success","e":"2000"}'],
            'unknown outcome' => ['{"c":1,"o":"maybe","e":2000}'],
        ];
    }
}
