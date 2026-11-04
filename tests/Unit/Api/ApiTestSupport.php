<?php
/**
 * Shared doubles for the tests of the legacy api/ payment classes.
 *
 * The payment classes create a real BuckarooClient in
 * PaymentMethod::getBuckarooClient(). The tests replace it with
 * RecordingBuckarooClient, which records every SDK call (payment method,
 * action, payload) and answers with a TransactionResponse built from an array,
 * so no test reaches the network.
 */

namespace BuckarooTest\Unit\Api;

use Buckaroo\Transaction\Response\TransactionResponse;
use BuckarooTest\Stubs;

require_once __DIR__ . '/StubsSupport.php';

/**
 * Stand-in for Buckaroo\BuckarooClient: method($type)->$action($payload).
 */
class RecordingBuckarooClient
{
    /** @var string|null gateway mode the client was created for */
    public $mode;

    /** @var array<int, array{type: string|null, action: string, payload: mixed}> */
    public $calls = [];

    /** @var array reply data of the TransactionResponse every call returns */
    public $reply;

    public function __construct($mode = null, array $reply = [])
    {
        $this->mode = $mode;
        $this->reply = $reply;
    }

    public function method($type = null)
    {
        return new RecordingPaymentFacade($this, $type);
    }

    /** @return array{type: string|null, action: string, payload: mixed} */
    public function lastCall(): array
    {
        if (empty($this->calls)) {
            throw new \LogicException('The Buckaroo client was not called');
        }

        return $this->calls[count($this->calls) - 1];
    }
}

/**
 * Stand-in for Buckaroo\PaymentMethods\PaymentFacade.
 */
class RecordingPaymentFacade
{
    /** @var RecordingBuckarooClient */
    private $client;

    /** @var string|null */
    private $type;

    public function __construct(RecordingBuckarooClient $client, $type)
    {
        $this->client = $client;
        $this->type = $type;
    }

    public function __call($action, $args)
    {
        $this->client->calls[] = [
            'type' => $this->type,
            'action' => $action,
            'payload' => $args[0] ?? null,
        ];

        return new TransactionResponse(null, $this->client->reply);
    }
}

/**
 * Module double answering getBuckarooConfigService() for PayPal and iDEAL.
 */
class ConfigServiceDouble
{
    /** @var array<string, mixed> "method.key" => value */
    public $values = [];

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function getConfigValue($method, $key)
    {
        return $this->values[$method . '.' . $key] ?? null;
    }
}

final class ApiFixtures
{
    /**
     * Load the legacy (non-autoloaded) module files. Tests call this from
     * setUp() rather than at file load, so the file-level statements of the
     * sources run while coverage is recorded.
     */
    public static function load(): void
    {
        $root = dirname(__DIR__, 3);
        require_once $root . '/library/logger.php';
        require_once $root . '/api/paymentmethods/paymentrequestfactory.php';
        require_once $root . '/api/paymentmethods/paymentmethod.php';
        require_once $root . '/api/paymentmethods/responsefactory.php';
        foreach (glob($root . '/api/paymentmethods/*/*.php') as $file) {
            if (basename($file) !== 'index.php') {
                require_once $file;
            }
        }
        require_once $root . '/classes/CarrierHandler.php';
    }

    /** Register the buckaroo3 module with a config service double. */
    public static function registerModule(array $configValues = []): ConfigServiceDouble
    {
        $configService = new ConfigServiceDouble($configValues);
        $module = new \Module();
        $module->stub('getBuckarooConfigService', $configService);
        \Module::$instances['buckaroo3'] = $module;

        return $configService;
    }

    /**
     * Make Config::getMode() resolve $mode from bk_payment_methods/bk_configuration.
     */
    public static function gatewayMode(string $mode): void
    {
        Stubs::db('/bk_payment_methods/', '7');
        Stubs::db('/bk_configuration/', json_encode(['mode' => $mode]));
    }

    /** A successful Buckaroo transaction reply. */
    public static function reply(int $status = 190, array $extra = []): array
    {
        return array_replace_recursive([
            'Key' => 'D3A1F6E5D4C3B2A1F6E5D4C3B2A1F6E5',
            'Status' => [
                'Code' => ['Code' => $status, 'Description' => 'Status ' . $status],
                'SubCode' => ['Code' => 'S990', 'Description' => 'The request was successful.'],
                'DateTime' => '2026-11-04T10:00:00',
            ],
            'RequiredAction' => null,
            'Services' => [[
                'Name' => 'ideal',
                'Action' => 'Pay',
                'Parameters' => [
                    ['Name' => 'consumerIBAN', 'Value' => 'NL44RABO0123456789'],
                    ['Name' => 'ConsumerName', 'Value' => 'J. de Tester'],
                ],
            ]],
            'Invoice' => 'XKBKNABJK_42',
            'Currency' => 'EUR',
            'AmountDebit' => 10.5,
            'IsTest' => true,
        ], $extra);
    }
}

/**
 * Collects PHP notices/warnings raised while running $callback (version
 * independent: a notice on PHP 7.4, a warning on PHP 8).
 */
final class ErrorCollector
{
    /**
     * @return array{0: mixed, 1: string[]} [return value, error messages]
     */
    public static function run(callable $callback): array
    {
        $errors = [];
        set_error_handler(static function ($severity, $message) use (&$errors) {
            $errors[] = $message;

            return true;
        });
        try {
            $result = $callback();
        } finally {
            restore_error_handler();
        }

        return [$result, $errors];
    }
}

/**
 * Signs push data the way the Buckaroo gateway does (Buckaroo\Handlers\Reply\HttpPost).
 */
final class PushSigner
{
    public const SECRET_KEY = 'a-test-secret-key-not-a-real-one';

    /**
     * @param array<string,string> $data
     */
    public static function sign(array $data): string
    {
        $data = array_filter($data, static function ($key) {
            $key = strtolower((string) $key);

            return $key !== 'brq_signature'
                && in_array(explode('_', $key)[0], ['brq', 'add', 'cust'], true);
        }, ARRAY_FILTER_USE_KEY);

        uksort($data, static function ($a, $b): int {
            return strcmp(strtolower((string) $a), strtolower((string) $b));
        });

        $signatureString = '';
        foreach ($data as $key => $value) {
            $signatureString .= $key . '=' . html_entity_decode((string) $value);
        }

        return sha1($signatureString . self::SECRET_KEY);
    }
}
