<?php
/**
 * Shared doubles for the Apple Pay express, giftcard and Click to Pay token tests.
 *
 * - ExpressTestModule: the real Buckaroo3 module class without its heavy
 *   constructor; the methods the tested code calls answer what the test set.
 * - GiftcardSdk: swaps the Buckaroo SDK giftcard payment method for
 *   RecordingSdkGiftCard, so the module's real GiftCard request class and
 *   BuckarooClient run, but the SDK answers with a TransactionResponse built
 *   from an array instead of reaching the network.
 */

namespace BuckarooTest\Unit\ExpressAndGiftcard;

use Buckaroo\PaymentMethods\GiftCard\GiftCard as SdkGiftCard;
use Buckaroo\PaymentMethods\PaymentMethodFactory;
use Buckaroo\PrestaShop\Src\ApplePay\ExpressSettings;
use Buckaroo\Transaction\Response\TransactionResponse;

require_once _PS_MODULE_DIR_ . 'buckaroo3/buckaroo3.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/applepayexpress.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/applygiftcard.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/clicktopaytoken.php';

/**
 * Buckaroo3 without its constructor (logger, configuration checks).
 */
class ExpressTestModule extends \Buckaroo3
{
    /** @var array|callable|null what getBuckarooFee() returns */
    public $fee;

    /** @var array<int, array> getBuckarooFee() arguments */
    public $feeCalls = [];

    /** @var ExpressSettings|null */
    public $settings;

    /** @var array<string, bool> method => isPaymentModeActive() */
    public $paymentModes = [];

    /** @var array<string, mixed> service id => service for get() */
    public $services = [];

    public function __construct()
    {
        $this->name = 'buckaroo3';
        $this->version = '5.4.0';
        $this->author = 'Buckaroo';
        $this->id = 7;
        $this->active = true;
        $this->context = \Context::getContext();
        $this->settings = new ExpressSettings(['merchant_guid' => 'GUID-1']);
    }

    public function getBuckarooFee($payment_method, $percentageBaseAmount = null)
    {
        $this->feeCalls[] = [$payment_method, $percentageBaseAmount];

        return is_callable($this->fee) ? call_user_func($this->fee, $payment_method, $percentageBaseAmount) : $this->fee;
    }

    public function getApplePayExpressSettings(): ExpressSettings
    {
        return $this->settings;
    }

    public function isPaymentModeActive($method)
    {
        return !empty($this->paymentModes[$method]);
    }

    public function get($serviceName)
    {
        if (!array_key_exists($serviceName, $this->services)) {
            throw new \RuntimeException('Unknown service ' . $serviceName);
        }

        return $this->services[$serviceName];
    }
}

/**
 * SDK giftcard method that records the request instead of posting it.
 */
class RecordingSdkGiftCard extends SdkGiftCard
{
    public function pay($model = null): TransactionResponse
    {
        return GiftcardSdk::answer('pay', $this->payload);
    }

    public function payRemainder($model = null)
    {
        return GiftcardSdk::answer('payRemainder', $this->payload);
    }
}

final class GiftcardSdk
{
    /** @var array<int, array{action: string, payload: array}> */
    public static $calls = [];

    /** @var array|\Throwable reply data of the next TransactionResponse, or an error to throw */
    public static $reply = [];

    /** @var array|null original SDK payment method map */
    private static $original;

    public static function install($reply): void
    {
        self::$calls = [];
        self::$reply = $reply;

        $property = new \ReflectionProperty(PaymentMethodFactory::class, 'payments');
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        if (self::$original === null) {
            self::$original = $property->getValue();
        }
        $property->setValue(null, [RecordingSdkGiftCard::class => ['giftcard', 'boekenbon', 'fashioncheque', 'tcs', 'vvvgiftcard']] + self::$original);
    }

    public static function uninstall(): void
    {
        if (self::$original === null) {
            return;
        }
        $property = new \ReflectionProperty(PaymentMethodFactory::class, 'payments');
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue(null, self::$original);
        self::$calls = [];
    }

    public static function answer(string $action, array $payload): TransactionResponse
    {
        self::$calls[] = ['action' => $action, 'payload' => $payload];
        if (self::$reply instanceof \Throwable) {
            throw self::$reply;
        }

        return new TransactionResponse(null, self::$reply);
    }

    /**
     * A giftcard reply.
     *
     * @param float|null $remainder RemainderAmount of a PayRemainder required action
     */
    public static function reply(int $status, ?float $remainder = null, string $group = '', string $key = 'TXKEY1', array $extra = []): array
    {
        $data = [
            'Key' => $key,
            'Status' => [
                'Code' => ['Code' => $status, 'Description' => 'Status ' . $status],
                'SubCode' => ['Code' => 'S990', 'Description' => 'Sub ' . $status],
                'DateTime' => '2026-11-04T10:00:00',
            ],
            'RequiredAction' => null,
            'Services' => [['Name' => 'boekenbon', 'Action' => 'Pay', 'Parameters' => []]],
            'Invoice' => 'gc_1_5',
            'Currency' => 'EUR',
            'AmountDebit' => 10,
            'IsTest' => true,
        ];
        if ($remainder !== null) {
            $data['RequiredAction'] = [
                'Name' => 'PayRemainder',
                'PayRemainderDetails' => [
                    'RemainderAmount' => $remainder,
                    'Currency' => 'EUR',
                    'GroupTransaction' => $group,
                ],
            ];
        }

        return array_replace($data, $extra);
    }
}

/**
 * The JSON endpoints call header(), which warns under PHPUnit because output
 * has already started. Those warnings are ignored; any other error still
 * reaches PHPUnit.
 */
trait RunsJsonEndpoints
{
    /**
     * @return mixed what $callback returns
     */
    protected static function withoutHeaderWarnings(callable $callback)
    {
        $previous = null;
        $previous = set_error_handler(static function ($errno, $errstr, $errfile = '', $errline = 0) use (&$previous) {
            if (strpos((string) $errstr, 'Cannot modify header information') !== false) {
                return true;
            }

            return is_callable($previous) ? call_user_func($previous, $errno, $errstr, $errfile, $errline) : false;
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Decoded JSON payloads the controller rendered.
     *
     * @return array<int, array>
     */
    protected static function jsonOutput(\Controller $controller): array
    {
        return array_map(static function ($json) {
            return json_decode((string) $json, true);
        }, $controller->ajaxOutput);
    }
}
