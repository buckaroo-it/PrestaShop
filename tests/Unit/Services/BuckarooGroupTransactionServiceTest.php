<?php

declare(strict_types=1);

namespace BuckarooTest\Unit\Services;

use Buckaroo\PrestaShop\Src\Service\BuckarooGroupTransactionService;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

final class BuckarooGroupTransactionServiceTest extends TestCase
{
    /** @var BuckarooGroupTransactionService */
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BuckarooGroupTransactionService();
    }

    /** @return array<int, array{method: string, sql: mixed, args: array}> */
    private static function queries(string $method): array
    {
        return array_values(array_filter(Stubs::$queries, function ($query) use ($method) {
            return $query['method'] === $method;
        }));
    }

    private static function row(array $values): array
    {
        return array_merge([
            'transaction_key' => 'TK',
            'group_transaction_id' => '',
            'amount' => '0',
            'refunded_amount' => '0',
            'card_code' => '',
        ], $values);
    }

    public function testSaveGroupTransactionInsertsAnEscapedSuccessRow(): void
    {
        $this->service->saveGroupTransaction(5, [
            'transaction_key' => "KEY'1",
            'group_transaction_id' => 'GROUP',
            'amount' => '12.5',
            'currency' => 'EUR',
            'card_code' => 'fashioncheque',
        ]);

        $insert = self::queries('insert')[0]['args'];
        self::assertSame('bk_group_transaction', $insert[0]);
        self::assertSame([
            'cart_id' => 5,
            'order_id' => null,
            'transaction_key' => "KEY\\'1",
            'group_transaction_id' => 'GROUP',
            'amount' => 12.5,
            'refunded_amount' => 0,
            'currency' => 'EUR',
            'card_code' => 'fashioncheque',
            'status' => 190,
        ], $insert[1]);
    }

    public function testSaveGroupTransactionDefaultsMissingValues(): void
    {
        $this->service->saveGroupTransaction(6, ['status' => '791']);

        $data = self::queries('insert')[0]['args'][1];
        self::assertSame('', $data['transaction_key']);
        self::assertSame('', $data['group_transaction_id']);
        self::assertSame(0.0, $data['amount']);
        self::assertSame('', $data['currency']);
        self::assertSame(791, $data['status']);
    }

    /**
     * @dataProvider statusUpdateProvider
     */
    public function testUpdateGroupTransactionStatus(string $key, int $status, ?string $expectedWhere): void
    {
        $this->service->updateGroupTransactionStatus($key, $status);

        $updates = self::queries('update');
        if ($expectedWhere === null) {
            self::assertSame([], $updates);

            return;
        }

        self::assertCount(1, $updates);
        self::assertSame(['bk_group_transaction', ['status' => $status], $expectedWhere], $updates[0]['args']);
    }

    public function statusUpdateProvider(): array
    {
        return [
            'success' => ['TK1', 190, "`transaction_key` = 'TK1'"],
            'failure' => ['TK1', 490, "`transaction_key` = 'TK1'"],
            'pending does not undo success' => ['TK1', 791, "`transaction_key` = 'TK1' AND `status` <> 190"],
            'escaped key' => ["T'K", 190, "`transaction_key` = 'T\\'K'"],
            'empty key' => ['', 190, null],
            'no status' => ['TK1', 0, null],
            'negative status' => ['TK1', -1, null],
        ];
    }

    public function testHasTransactionLooksUpTheCartAndKey(): void
    {
        Stubs::db('/SELECT 1 FROM `ps_bk_group_transaction`/', function ($sql) {
            return strpos($sql, "`transaction_key` = 'TK'") !== false ? '1' : false;
        });

        self::assertTrue($this->service->hasTransaction(5, 'TK'));
        self::assertFalse($this->service->hasTransaction(5, 'OTHER'));
        self::assertStringContainsString('`cart_id` = 5', Stubs::sql('getValue')[0]);
    }

    public function testHasTransactionRejectsInvalidInputWithoutQuerying(): void
    {
        self::assertFalse($this->service->hasTransaction(0, 'TK'));
        self::assertFalse($this->service->hasTransaction(5, ''));
        self::assertSame([], Stubs::$queries);
    }

    public function testLinkOrderToCartUpdatesAllRowsOfTheCart(): void
    {
        $this->service->linkOrderToCart(5, 77);

        self::assertSame(
            ['bk_group_transaction', ['order_id' => 77], '`cart_id` = 5'],
            self::queries('update')[0]['args']
        );
    }

    public function testItemsAreOnlyReadForAValidCart(): void
    {
        self::assertSame([], $this->service->getGroupTransactionItems(0));
        self::assertSame([], Stubs::$queries);

        Stubs::db('/FROM `ps_bk_group_transaction`/', false);
        self::assertSame([], $this->service->getGroupTransactionItems(5), 'a failed query yields no rows');

        $sql = Stubs::sql('executeS')[0];
        self::assertStringContainsString('`cart_id` = 5', $sql);
        self::assertStringContainsString('`status` = 190', $sql);
        self::assertStringContainsString('ORDER BY `created_at` ASC', $sql);
    }

    public function testAlreadyPaidAndRemainingAmountUseNetAmounts(): void
    {
        Stubs::db('/FROM `ps_bk_group_transaction`/', [
            self::row(['amount' => '10.10', 'refunded_amount' => '0']),
            self::row(['amount' => '5.555', 'refunded_amount' => '1.00']),
        ]);

        self::assertSame(14.66, $this->service->getAlreadyPaid(5));
        self::assertSame(5.34, $this->service->getRemainingAmount(5, 20.0));
        self::assertSame(0.0, $this->service->getRemainingAmount(5, 10.0), 'never negative');
        self::assertSame(0.0, $this->service->getAlreadyPaid(0));
    }

    public function testOriginalTransactionKeyIsTheNewestGroupTransactionId(): void
    {
        Stubs::db('/FROM `ps_bk_group_transaction`/', [
            self::row(['group_transaction_id' => 'OLD']),
            self::row(['group_transaction_id' => 'NEW']),
            self::row(['group_transaction_id' => '']),
        ]);

        self::assertSame('NEW', $this->service->getOriginalTransactionKey(5));
    }

    public function testOriginalTransactionKeyIsEmptyWithoutGroupTransactions(): void
    {
        self::assertSame('', $this->service->getOriginalTransactionKey(0));

        Stubs::db('/FROM `ps_bk_group_transaction`/', [self::row([])]);
        self::assertSame('', $this->service->getOriginalTransactionKey(5));
    }

    public function testDisplayItemsLabelEachCardAndSkipFullyRefundedRows(): void
    {
        Stubs::db('/FROM `ps_bk_group_transaction`/', [
            self::row(['transaction_key' => 'A', 'amount' => '10', 'card_code' => 'fashioncheque']),
            self::row(['transaction_key' => 'B', 'amount' => '5', 'refunded_amount' => '5', 'card_code' => 'boekenbon']),
            self::row(['transaction_key' => 'C', 'amount' => '2.499', 'card_code' => 'vvvgiftcard']),
            self::row(['transaction_key' => 'D', 'amount' => '1', 'card_code' => '']),
            self::row(['transaction_key' => 'E', 'amount' => '3', 'card_code' => 'nameless']),
        ]);
        Stubs::db('/FROM `ps_bk_giftcards`/', function ($sql) {
            if (strpos($sql, "'fashioncheque'") !== false) {
                return ['name' => 'Fashion Cheque'];
            }
            if (strpos($sql, "'nameless'") !== false) {
                return ['name' => ''];
            }

            return false;
        });

        self::assertSame([
            ['label' => 'Paid with Fashion Cheque', 'amount' => 10.0, 'card_code' => 'fashioncheque', 'transaction_key' => 'A'],
            ['label' => 'Paid with Vvvgiftcard Giftcard', 'amount' => 2.5, 'card_code' => 'vvvgiftcard', 'transaction_key' => 'C'],
            ['label' => 'Giftcard', 'amount' => 1.0, 'card_code' => '', 'transaction_key' => 'D'],
            ['label' => 'Paid with Nameless Giftcard', 'amount' => 3.0, 'card_code' => 'nameless', 'transaction_key' => 'E'],
        ], $this->service->getDisplayItems(5));

        self::assertCount(3, Stubs::sql('getRow'), 'no lookup for an empty card code or a skipped row');
    }
}
