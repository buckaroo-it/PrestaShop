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

namespace BuckarooTest\Unit\Data;

use Buckaroo\PrestaShop\Src\Repository\RawBuckarooFeeRepository;
use Buckaroo\PrestaShop\Src\Repository\RawCreditCardsRepository;
use Buckaroo\PrestaShop\Src\Repository\RawGiftCardsRepository;
use Buckaroo\PrestaShop\Src\Repository\RawOrderingRepository;
use Buckaroo\PrestaShop\Src\Repository\RawPaymentMethodRepository;
use Buckaroo\PrestaShop\Src\Repository\RawTransactionModeRepository;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

/**
 * The raw (Db::getInstance) repositories used by the installer and the payment flow.
 */
class RawRepositoriesTest extends TestCase
{
    /** @return array<int, array{method: string, sql: string, args: array}> */
    private static function queries(string $method): array
    {
        return array_values(array_filter(Stubs::$queries, function ($query) use ($method) {
            return $query['method'] === $method;
        }));
    }

    /** @return array<string, array> rows inserted into $table, keyed by the row's $key column */
    private static function insertedRows(string $table, string $key): array
    {
        $rows = [];
        foreach (self::queries('insert') as $query) {
            if ($query['args'][0] === $table) {
                $rows[$query['args'][1][$key]] = $query['args'][1];
            }
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // RawPaymentMethodRepository
    // ------------------------------------------------------------------

    public function testInsertPaymentMethodsClearsBothTablesThenInsertsEveryMethod(): void
    {
        $data = (new RawPaymentMethodRepository())->insertPaymentMethods();

        self::assertSame(
            ['DELETE FROM ps_bk_payment_methods', 'DELETE FROM ps_bk_configuration'],
            Stubs::sql('execute')
        );

        $methods = self::insertedRows('bk_payment_methods', 'name');
        self::assertCount(count($data), $methods);
        self::assertCount(31, $methods);
        self::assertSame(array_column($data, 'name'), array_keys($methods));
        self::assertSame('iDEAL | Wero', $methods['ideal']['label']);
        self::assertSame('payment_ideal.tpl', $methods['ideal']['template']);
        self::assertSame('1', $methods['ideal']['is_payment_method']);
        // iDIN is a verification, not a payment method.
        self::assertSame('0', $methods['idin']['is_payment_method']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $methods['ideal']['created_at']);
    }

    /**
     * @return array<string, array{0: string, 1: array}>
     */
    public function defaultConfigurationProvider(): array
    {
        return [
            'creditcard' => ['creditcard', ['mode' => 'off', 'show_issuers' => true, 'display_type' => 'radio']],
            'clicktopay' => ['clicktopay', ['mode' => 'off', 'client_id' => '', 'client_secret' => '', 'merchant_identifier' => '']],
            'paybybank' => ['paybybank', ['mode' => 'off', 'display_type' => 'radio']],
            'in3' => ['in3', ['mode' => 'off', 'version' => 'V3']],
            'paypal' => ['paypal', ['mode' => 'off', 'seller_protection' => '0']],
            'afterpay' => ['afterpay', ['mode' => 'off', 'customer_type' => 'B2C']],
            'billink' => ['billink', ['mode' => 'off', 'customer_type' => 'B2C']],
            'payperemail' => ['payperemail', ['mode' => 'off', 'send_instruction_email' => '1', 'due_days' => '7', 'allowed_payments' => 'ideal']],
            'transfer' => ['transfer', ['mode' => 'off', 'send_instruction_email' => '0', 'due_days' => '14']],
            'idin' => ['idin', ['mode' => 'off', 'display_mode' => 'global']],
            'giftcard' => ['giftcard', ['mode' => 'off', 'display_in_checkout' => 'separate']],
            'ideal (defaults only)' => ['ideal', ['mode' => 'off']],
        ];
    }

    /**
     * @dataProvider defaultConfigurationProvider
     */
    public function testEachPaymentMethodGetsItsDefaultConfiguration(string $name, array $expected): void
    {
        // Track which bk_payment_methods id each method received.
        $idByName = [];
        Stubs::db('/INSERT INTO `ps_bk_payment_methods`/', function ($sql, $method, $args) use (&$idByName) {
            $idByName[$args[1]['name']] = Stubs::$nextId;

            return true;
        });

        (new RawPaymentMethodRepository())->insertPaymentMethods();

        $configs = self::insertedRows('bk_configuration', 'configurable_id');
        self::assertCount(31, $configs);
        self::assertArrayHasKey($idByName[$name], $configs);
        self::assertSame($expected, json_decode($configs[$idByName[$name]]['value'], true));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function paymentMethodWriteFailureProvider(): array
    {
        return [
            'clearing payment methods' => ['/DELETE FROM ps_bk_payment_methods/', 'Could not clear payment methods table'],
            'clearing configuration' => ['/DELETE FROM ps_bk_configuration/', 'Could not clear configuration table'],
            'inserting a method' => ['/INSERT INTO `ps_bk_payment_methods`/', 'Could not insert payment method'],
            'inserting its configuration' => ['/INSERT INTO `ps_bk_configuration`/', 'Could not insert configuration'],
        ];
    }

    /**
     * @dataProvider paymentMethodWriteFailureProvider
     */
    public function testInsertPaymentMethodsThrowsWhenAWriteFails(string $failingSql, string $message): void
    {
        Stubs::db($failingSql, false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($message);

        (new RawPaymentMethodRepository())->insertPaymentMethods();
    }

    public function testGetPaymentMethodsFromDbSelectsAllIds(): void
    {
        Stubs::db('/FROM `ps_bk_payment_methods`/', [['id' => 1], ['id' => 2]]);

        self::assertSame([['id' => 1], ['id' => 2]], (new RawPaymentMethodRepository())->getPaymentMethodsFromDB());
        self::assertSame(['SELECT id FROM `ps_bk_payment_methods`'], Stubs::sql('executeS'));
    }

    public function testGetPaymentMethodIdEscapesTheName(): void
    {
        Stubs::db('/FROM `ps_bk_payment_methods`/', '7');

        self::assertSame('7', (new RawPaymentMethodRepository())->getPaymentMethodId('ide"al'));
        self::assertSame(['SELECT id FROM `ps_bk_payment_methods` WHERE (name = "ide\\"al")'], Stubs::sql('getValue'));
    }

    public function testGetPaymentMethodsLabelEscapesTheName(): void
    {
        Stubs::db('/FROM `ps_bk_payment_methods`/', 'iDEAL');

        self::assertSame('iDEAL', (new RawPaymentMethodRepository())->getPaymentMethodsLabel("o'ideal"));
        self::assertSame(['SELECT label FROM `ps_bk_payment_methods` WHERE (name = "o\\\'ideal")'], Stubs::sql('getValue'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function modeProvider(): array
    {
        return [
            'live' => ['live'],
            'test' => ['test'],
            'off' => ['off'],
        ];
    }

    /**
     * @dataProvider modeProvider
     */
    public function testGetPaymentMethodModeReadsTheModeOfTheMethodConfiguration(string $mode): void
    {
        Stubs::db('/FROM `ps_bk_payment_methods`/', '12');
        Stubs::db('/FROM `ps_bk_configuration`/', json_encode(['mode' => $mode, 'other' => 1]));

        self::assertSame($mode, (new RawPaymentMethodRepository())->getPaymentMethodMode('ideal'));
        self::assertSame(
            [
                'SELECT id FROM `ps_bk_payment_methods` WHERE (name = "ideal")',
                'SELECT value FROM `ps_bk_configuration` WHERE (configurable_id = 12)',
            ],
            Stubs::sql('getValue')
        );
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public function invalidModeConfigurationProvider(): array
    {
        return [
            'no configuration row' => [false, 'Configuration not found for payment id 12'],
            'invalid json' => ['{not json', 'JSON decode error'],
            'no mode key' => [json_encode(['version' => 'V3']), 'Mode not set for payment id 12'],
        ];
    }

    /**
     * @dataProvider invalidModeConfigurationProvider
     */
    public function testGetPaymentMethodModeThrowsOnUnusableConfiguration($configValue, string $message): void
    {
        Stubs::db('/FROM `ps_bk_payment_methods`/', '12');
        Stubs::db('/FROM `ps_bk_configuration`/', $configValue);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($message);

        (new RawPaymentMethodRepository())->getPaymentMethodMode('ideal');
    }

    // ------------------------------------------------------------------
    // RawGiftCardsRepository / RawCreditCardsRepository
    // ------------------------------------------------------------------

    public function testInsertGiftCardsClearsTheTableAndInsertsTheDefaults(): void
    {
        $repository = new RawGiftCardsRepository();
        $data = $repository->insertGiftCards();

        self::assertSame($repository->getGiftCardsData(), $data);
        self::assertSame(['DELETE FROM ps_bk_giftcards'], Stubs::sql('execute'));

        $cards = self::insertedRows('bk_giftcards', 'code');
        self::assertSame(array_column($data, 'code'), array_keys($cards));
        self::assertSame(
            ['logo' => 'FashionCheque.svg', 'code' => 'fashioncheque', 'name' => 'Fashion Cheque'],
            array_intersect_key($cards['fashioncheque'], ['logo' => 1, 'code' => 1, 'name' => 1])
        );
        self::assertArrayHasKey('created_at', $cards['boekenbon']);
    }

    public function testInsertCreditCardsClearsTheTableAndInsertsTheDefaults(): void
    {
        $repository = new RawCreditCardsRepository();
        $data = $repository->insertCreditCards();

        self::assertSame($repository->getCreditCardsData(), $data);
        self::assertSame(['DELETE FROM ps_bk_creditcards'], Stubs::sql('execute'));

        $cards = self::insertedRows('bk_creditcards', 'service_code');
        self::assertSame(array_column($data, 'service_code'), array_keys($cards));
        self::assertSame('VISA', $cards['visa']['name']);
        self::assertSame('Visa.svg', $cards['visa']['icon']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public function cardWriteFailureProvider(): array
    {
        return [
            'gift cards: clear' => [RawGiftCardsRepository::class, 'insertGiftCards', '/DELETE FROM ps_bk_giftcards/', 'Could not clear gift cards table'],
            'gift cards: insert' => [RawGiftCardsRepository::class, 'insertGiftCards', '/INSERT INTO `ps_bk_giftcards`/', 'Could not insert gift cards'],
            'credit cards: clear' => [RawCreditCardsRepository::class, 'insertCreditCards', '/DELETE FROM ps_bk_creditcards/', 'Could not clear credit cards table'],
            'credit cards: insert' => [RawCreditCardsRepository::class, 'insertCreditCards', '/INSERT INTO `ps_bk_creditcards`/', 'Could not insert Credit cards'],
        ];
    }

    /**
     * @dataProvider cardWriteFailureProvider
     */
    public function testCardInsertsThrowWhenAWriteFails(string $class, string $method, string $failingSql, string $message): void
    {
        Stubs::db($failingSql, false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($message);

        (new $class())->{$method}();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public function cardTableProvider(): array
    {
        return [
            'gift cards' => [RawGiftCardsRepository::class, 'getGiftCardsFromDB', 'ps_bk_giftcards'],
            'credit cards' => [RawCreditCardsRepository::class, 'getCreditCardsFromDB', 'ps_bk_creditcards'],
        ];
    }

    /**
     * @dataProvider cardTableProvider
     */
    public function testCardsAreReadFromTheirTable(string $class, string $method, string $table): void
    {
        $rows = [['id' => 1, 'code' => 'x'], ['id' => 2, 'code' => 'y']];
        Stubs::db('/FROM `' . $table . '`/', $rows);

        self::assertSame($rows, (new $class())->{$method}());
        self::assertSame(['SELECT * FROM `' . $table . '`'], Stubs::sql('executeS'));
    }

    /**
     * @dataProvider cardTableProvider
     */
    public function testReadingAnEmptyCardTableThrows(string $class, string $method, string $table): void
    {
        Stubs::db('/FROM `' . $table . '`/', []);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Database error: Could not fetch (gift|credit) cards/');

        (new $class())->{$method}();
    }

    // ------------------------------------------------------------------
    // RawOrderingRepository
    // ------------------------------------------------------------------

    public function testInsertCountryOrderingDefaultsToEveryPaymentMethodInDatabase(): void
    {
        Stubs::db('/FROM `ps_bk_payment_methods`/', [['id' => '3'], ['id' => '1'], ['id' => '2']]);

        self::assertTrue((new RawOrderingRepository())->insertCountryOrdering());

        self::assertSame(['DELETE FROM ps_bk_ordering'], Stubs::sql('execute'));
        $inserts = self::queries('insert');
        self::assertCount(1, $inserts);
        self::assertSame('bk_ordering', $inserts[0]['args'][0]);
        $row = $inserts[0]['args'][1];
        // A null country is the "all countries" ordering.
        self::assertSame('', $row['country_id']);
        self::assertSame('[\\"3\\",\\"1\\",\\"2\\"]', $row['value']);
        self::assertSame(['3', '1', '2'], json_decode(stripslashes($row['value']), true));
    }

    public function testInsertCountryOrderingUsesTheGivenCountryAndMethods(): void
    {
        self::assertTrue((new RawOrderingRepository())->insertCountryOrdering(21, [5, 4]));

        self::assertSame([], Stubs::sql('executeS'), 'payment methods are not read when given');
        $row = self::queries('insert')[0]['args'][1];
        self::assertSame('21', $row['country_id']);
        self::assertSame('[5,4]', $row['value']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function orderingWriteFailureProvider(): array
    {
        return [
            'clear' => ['/DELETE FROM ps_bk_ordering/', 'Could not clear ordering table'],
            'insert' => ['/INSERT INTO `ps_bk_ordering`/', 'Unable to insert country'],
        ];
    }

    /**
     * @dataProvider orderingWriteFailureProvider
     */
    public function testInsertCountryOrderingThrowsWhenAWriteFails(string $failingSql, string $message): void
    {
        Stubs::db($failingSql, false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($message);

        (new RawOrderingRepository())->insertCountryOrdering(1, [1]);
    }

    // ------------------------------------------------------------------
    // RawTransactionModeRepository
    // ------------------------------------------------------------------

    public function testRecordStoresTheInvoiceModeWithInsertIgnore(): void
    {
        self::assertTrue((new RawTransactionModeRepository())->record("INV'1", 'live'));

        $insert = self::queries('insert')[0];
        self::assertSame(RawTransactionModeRepository::TABLE, $insert['args'][0]);
        self::assertSame("INV\\'1", $insert['args'][1]['invoice']);
        self::assertSame('live', $insert['args'][1]['mode']);
        self::assertSame(\Db::INSERT_IGNORE, $insert['args'][2]);
    }

    public function testRecordReturnsTheDatabaseResult(): void
    {
        Stubs::db('/INSERT INTO `ps_bk_transaction_mode`/', false);

        self::assertFalse((new RawTransactionModeRepository())->record('INV1', 'test'));
    }

    public function testRecordIgnoresAnEmptyInvoice(): void
    {
        self::assertFalse((new RawTransactionModeRepository())->record('', 'live'));
        self::assertSame([], Stubs::$queries);
    }

    public function testRecordLogsAndReturnsFalseWhenTheDatabaseThrows(): void
    {
        Stubs::db('/INSERT INTO `ps_bk_transaction_mode`/', function () {
            throw new \RuntimeException('table missing');
        });

        self::assertFalse((new RawTransactionModeRepository())->record('INV1', 'live'));
        self::assertSame(
            [['Failed to store Buckaroo gateway mode: table missing', 3]],
            array_map(function ($args) {
                return array_slice($args, 0, 2);
            }, Stubs::callsTo('PrestaShopLogger::addLog'))
        );
    }

    public function testGetModesReturnsTheModesOfTheInvoice(): void
    {
        Stubs::db('/FROM `ps_bk_transaction_mode`/', [['mode' => 'live'], ['mode' => 'test']]);

        self::assertSame(['live', 'test'], (new RawTransactionModeRepository())->getModes("INV'2"));
        self::assertSame(
            ["SELECT `mode` FROM `ps_bk_transaction_mode` WHERE `invoice` = 'INV\\'2'"],
            Stubs::sql('executeS')
        );
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public function nonArrayResultProvider(): array
    {
        return [
            'false' => [false],
            'null' => [null],
        ];
    }

    /**
     * @dataProvider nonArrayResultProvider
     */
    public function testGetModesReturnsAnEmptyListForANonArrayResult($result): void
    {
        Stubs::db('/FROM `ps_bk_transaction_mode`/', $result);

        self::assertSame([], (new RawTransactionModeRepository())->getModes('INV1'));
    }

    public function testGetModesIgnoresAnEmptyInvoice(): void
    {
        self::assertSame([], (new RawTransactionModeRepository())->getModes(''));
        self::assertSame([], Stubs::$queries);
    }

    public function testGetModesLogsAndReturnsNothingWhenTheDatabaseThrows(): void
    {
        Stubs::db('/FROM `ps_bk_transaction_mode`/', function () {
            throw new \RuntimeException('gone');
        });

        self::assertSame([], (new RawTransactionModeRepository())->getModes('INV1'));
        self::assertSame('Failed to read Buckaroo gateway mode: gone', Stubs::callsTo('PrestaShopLogger::addLog')[0][0]);
    }

    // ------------------------------------------------------------------
    // RawBuckarooFeeRepository
    // ------------------------------------------------------------------

    public function testInsertFeeCastsAndEscapesEveryColumn(): void
    {
        self::assertTrue((new RawBuckarooFeeRepository())->insertFee("REF'1", '5', '42', '1.5', '1.815', 'EUR'));

        $insert = self::queries('insert')[0];
        self::assertSame('bk_buckaroo_fee', $insert['args'][0]);
        $row = $insert['args'][1];
        unset($row['created_at']);
        self::assertSame([
            'reference' => "REF\\'1",
            'id_cart' => 5,
            'id_order' => 42,
            'buckaroo_fee_tax_excl' => 1.5,
            'buckaroo_fee_tax_incl' => 1.815,
            'currency' => 'EUR',
        ], $row);
    }

    public function testInsertFeeLogsAndReturnsFalseWhenTheDatabaseThrows(): void
    {
        Stubs::db('/INSERT INTO `ps_bk_buckaroo_fee`/', function () {
            throw new \RuntimeException('duplicate');
        });

        self::assertFalse((new RawBuckarooFeeRepository())->insertFee('REF', 1, 2, 1, 1, 'EUR'));
        self::assertSame('Failed to insert buckaroo fee: duplicate', Stubs::callsTo('PrestaShopLogger::addLog')[0][0]);
    }

    public function testGetFeeByOrderIdCastsTheOrderId(): void
    {
        Stubs::db('/FROM ps_bk_buckaroo_fee/', ['id_order' => 42, 'buckaroo_fee_tax_incl' => 2.5]);

        self::assertSame(['id_order' => 42, 'buckaroo_fee_tax_incl' => 2.5], (new RawBuckarooFeeRepository())->getFeeByOrderId('42 OR 1=1'));
        self::assertSame(['SELECT * FROM ps_bk_buckaroo_fee WHERE id_order = 42'], Stubs::sql('getRow'));
    }

    public function testMarkFeeRefundedUpdatesTheOrderRow(): void
    {
        self::assertTrue((new RawBuckarooFeeRepository())->markFeeRefunded(42));

        $update = self::queries('update')[0];
        self::assertSame(['bk_buckaroo_fee', ['fee_refunded' => 1], 'id_order = 42'], $update['args']);
    }

    public function testMarkFeeRefundedLogsAndReturnsFalseWhenTheDatabaseThrows(): void
    {
        Stubs::db('/UPDATE `ps_bk_buckaroo_fee`/', function () {
            throw new \RuntimeException('locked');
        });

        self::assertFalse((new RawBuckarooFeeRepository())->markFeeRefunded(42));
        self::assertSame('Failed to mark buckaroo fee as refunded: locked', Stubs::callsTo('PrestaShopLogger::addLog')[0][0]);
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public function feeRefundedProvider(): array
    {
        return [
            'refunded' => [['fee_refunded' => '1'], true],
            'not refunded' => [['fee_refunded' => '0'], false],
            'column missing' => [['id_order' => 42], false],
            'no fee row' => [false, false],
        ];
    }

    /**
     * @dataProvider feeRefundedProvider
     */
    public function testIsFeeRefunded($row, bool $expected): void
    {
        Stubs::db('/FROM ps_bk_buckaroo_fee/', $row);

        self::assertSame($expected, (new RawBuckarooFeeRepository())->isFeeRefunded(42));
    }
}
