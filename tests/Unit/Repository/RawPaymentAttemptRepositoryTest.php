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

namespace Buckaroo\PrestaShop\Tests\Unit\Repository;

use Buckaroo\PrestaShop\Src\Repository\RawPaymentAttemptRepository;
use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentStatus;
use PHPUnit\Framework\TestCase;

/**
 * Payment attempts let a push or return find the cart before the order exists (#337).
 */
final class RawPaymentAttemptRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        \Db::reset();
    }

    public function testAttemptReferencesNeverLookLikeOrderReferences(): void
    {
        self::assertSame('BK12', RawPaymentAttemptRepository::referenceFor(12));
        self::assertTrue(RawPaymentAttemptRepository::isAttemptReference('BK12'));

        // PrestaShop order references are 9 letters; giftcard invoices start with gc_.
        self::assertFalse(RawPaymentAttemptRepository::isAttemptReference('XKBKNABJK'));
        self::assertFalse(RawPaymentAttemptRepository::isAttemptReference('BKAAAAAAA'));
        self::assertFalse(RawPaymentAttemptRepository::isAttemptReference('BK0'));
        self::assertFalse(RawPaymentAttemptRepository::isAttemptReference('gc_1700000000'));
        self::assertFalse(RawPaymentAttemptRepository::isAttemptReference(''));
    }

    public function testTheInvoiceParserReadsTheAttemptAndCart(): void
    {
        self::assertSame([34, 'BK12'], BuckarooPaymentStatus::parseInvoice('BK12_34'));
    }

    public function testStartStoresTheAttemptWithItsReference(): void
    {
        $attempt = (new RawPaymentAttemptRepository())->start(34, 1, 'ideal', 'iDEAL', 'EUR');

        self::assertNotNull($attempt);
        self::assertSame('BK1', $attempt['reference']);
        self::assertSame(34, $attempt['id_cart']);
        self::assertSame(RawPaymentAttemptRepository::STATUS_STARTED, $attempt['status']);
        self::assertSame('iDEAL', $attempt['payment_label']);
    }

    public function testFindByInvoiceRequiresTheMatchingCart(): void
    {
        $repository = new RawPaymentAttemptRepository();
        $repository->start(34, 1, 'ideal', 'iDEAL', 'EUR');

        self::assertNotNull($repository->findByInvoice('BK1', 34));
        // A signed push for another cart must not reach this attempt.
        self::assertNull($repository->findByInvoice('BK1', 35));
        self::assertNull($repository->findByInvoice('BK2', 34));
        self::assertNull($repository->findByInvoice('ABCDEFGHI', 34));
    }

    public function testFindLatestByCartReturnsTheNewestAttempt(): void
    {
        $repository = new RawPaymentAttemptRepository();
        $repository->start(34, 1, 'ideal', 'iDEAL', 'EUR');
        $repository->start(34, 1, 'creditcard', 'Cards', 'EUR');

        self::assertSame('BK2', $repository->findLatestByCart(34)['reference']);
        self::assertNull($repository->findLatestByCart(99));
    }

    public function testStatusFollowsThePaymentAndPaidIsFinal(): void
    {
        $repository = new RawPaymentAttemptRepository();
        $repository->start(34, 1, 'ideal', 'iDEAL', 'EUR');

        self::assertTrue($repository->markPending(1, 'KEY1'));
        self::assertSame(RawPaymentAttemptRepository::STATUS_PENDING, $repository->findById(1)['status']);
        self::assertSame('KEY1', $repository->findById(1)['transaction_key']);

        self::assertTrue($repository->markPaid(1, 77, 'KEY1'));
        $paid = $repository->findById(1);
        self::assertSame(RawPaymentAttemptRepository::STATUS_PAID, $paid['status']);
        self::assertSame(77, $paid['id_order']);
        self::assertFalse(RawPaymentAttemptRepository::hasAmountMismatch($paid));

        // A late pending or failed push never undoes a paid attempt.
        self::assertFalse($repository->markPending(1));
        self::assertFalse($repository->markFailed(1));
        self::assertSame(RawPaymentAttemptRepository::STATUS_PAID, $repository->findById(1)['status']);
    }

    public function testAMismatchedAmountIsRemembered(): void
    {
        $repository = new RawPaymentAttemptRepository();
        $repository->start(34, 1, 'ideal', 'iDEAL', 'EUR');
        $repository->markPaid(1, 77, 'KEY1', true);

        self::assertTrue(RawPaymentAttemptRepository::hasAmountMismatch($repository->findById(1)));
        self::assertFalse(RawPaymentAttemptRepository::hasAmountMismatch(null));
    }

    public function testFailedAttemptsKeepTheirTransactionKey(): void
    {
        $repository = new RawPaymentAttemptRepository();
        $repository->start(34, 1, 'ideal', 'iDEAL', 'EUR');

        self::assertTrue($repository->markFailed(1, 'KEY9'));
        self::assertSame(RawPaymentAttemptRepository::STATUS_FAILED, $repository->findById(1)['status']);
        self::assertSame('KEY9', $repository->findById(1)['transaction_key']);
    }

    public function testTheTableHasAUniqueReferenceAndACartIndex(): void
    {
        $sql = RawPaymentAttemptRepository::createTableSql();

        self::assertStringContainsString('ps_bk_payment_attempt', $sql);
        self::assertStringContainsString('UNIQUE KEY `reference`', $sql);
        self::assertStringContainsString('KEY `id_cart`', $sql);
        self::assertStringContainsString('`amount_mismatch`', $sql);
    }
}
