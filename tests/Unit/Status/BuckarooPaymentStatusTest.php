<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Status;

use Buckaroo\BuckarooClient;
use Buckaroo\Handlers\Reply\ReplyHandler;
use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentStatus;
use PHPUnit\Framework\TestCase;

/**
 * Pending (790-793) must never count as paid; only 190 does.
 */
class BuckarooPaymentStatusTest extends TestCase
{
    private const WEBSITE_KEY = 'TESTWEBSITEKEY';
    private const SECRET_KEY = 'a-test-secret-key-not-a-real-one';

    /**
     * @return array<string,array{0:int|string}>
     */
    public function pendingCodes(): array
    {
        return [
            '790' => [790],
            '791' => ['791'],
            '792' => [792],
            '793' => ['793'],
        ];
    }

    /**
     * @dataProvider pendingCodes
     */
    public function testPendingStatusIsNotPaid($code): void
    {
        $this->assertFalse(BuckarooPaymentStatus::isPaid($code));
        $this->assertTrue(BuckarooPaymentStatus::isPending($code));
    }

    public function testOnlySuccessIsPaid(): void
    {
        $this->assertTrue(BuckarooPaymentStatus::isPaid('190'));
        $this->assertFalse(BuckarooPaymentStatus::isPaid('490'));
        $this->assertFalse(BuckarooPaymentStatus::isPaid('890'));
        $this->assertFalse(BuckarooPaymentStatus::isPaid(null));
        $this->assertFalse(BuckarooPaymentStatus::isPaid('BUCKAROO_PENDING_PAYMENT'));
    }

    public function testSignedReturnWith791IsValidButNotPaid(): void
    {
        $return = [
            'brq_amount' => '26.62',
            'brq_currency' => 'EUR',
            'brq_invoicenumber' => 'ABCDEFGHI_42',
            'brq_payment_method' => 'ideal',
            'brq_statuscode' => '791',
            'brq_transactions' => 'F6E5D4C3B2A1F6E5D4C3B2A1F6E5D4C3',
        ];
        $return['brq_signature'] = $this->sign($return);

        $buckaroo = new BuckarooClient(self::WEBSITE_KEY, self::SECRET_KEY);
        $handler = new ReplyHandler($buckaroo->client()->config(), $return);
        $handler->validate();

        $this->assertTrue($handler->isValid());
        $this->assertFalse(BuckarooPaymentStatus::isPaid($return['brq_statuscode']));
    }

    public function testGiftcardPendingWithoutRemainderDoesNotComplete(): void
    {
        $this->assertSame(
            BuckarooPaymentStatus::GIFTCARD_PENDING,
            BuckarooPaymentStatus::giftcardOutcome(791, 0.0, 50.0, '')
        );
        $this->assertSame(
            BuckarooPaymentStatus::GIFTCARD_PENDING,
            BuckarooPaymentStatus::giftcardOutcome(790, 0.0, 50.0, 'GROUPTX')
        );
    }

    public function testGiftcardPendingWithUnusableRemainderDoesNotApply(): void
    {
        // Remainder equal to the charge means nothing was taken from the card.
        $this->assertSame(
            BuckarooPaymentStatus::GIFTCARD_PENDING,
            BuckarooPaymentStatus::giftcardOutcome(790, 50.0, 50.0, 'GROUPTX')
        );
        $this->assertSame(
            BuckarooPaymentStatus::GIFTCARD_PENDING,
            BuckarooPaymentStatus::giftcardOutcome(790, 20.0, 50.0, '')
        );
    }

    public function testGiftcardPayRemainderIsPartial(): void
    {
        $this->assertSame(
            BuckarooPaymentStatus::GIFTCARD_PARTIAL,
            BuckarooPaymentStatus::giftcardOutcome(790, 20.0, 50.0, 'GROUPTX')
        );
    }

    public function testGiftcardSuccessWithoutRemainderIsPaid(): void
    {
        $this->assertSame(
            BuckarooPaymentStatus::GIFTCARD_PAID,
            BuckarooPaymentStatus::giftcardOutcome('190', 0.0, 50.0, '')
        );
    }

    public function testGiftcardFailureIsFailed(): void
    {
        $this->assertSame(
            BuckarooPaymentStatus::GIFTCARD_FAILED,
            BuckarooPaymentStatus::giftcardOutcome(490, 0.0, 50.0, '')
        );
    }

    public function testGiftcardInvoiceResolvesTheCart(): void
    {
        $invoice = BuckarooPaymentStatus::giftcardInvoice(42, 1760000000);

        [$cartId, $reference] = BuckarooPaymentStatus::parseInvoice($invoice);

        $this->assertSame(42, $cartId);
        $this->assertTrue(BuckarooPaymentStatus::isGiftcardReference($reference));
    }

    public function testOrderInvoiceStillResolvesCartAndReference(): void
    {
        $this->assertSame([42, 'ABCDEFGHI'], BuckarooPaymentStatus::parseInvoice('ABCDEFGHI_42'));
        $this->assertSame([0, 'ABCDEFGHI'], BuckarooPaymentStatus::parseInvoice('ABCDEFGHI'));
        $this->assertFalse(BuckarooPaymentStatus::isGiftcardReference('ABCDEFGHI'));
    }

    /**
     * Same signing scheme as the SDK reply handler.
     *
     * @param array<string,string> $data
     */
    private function sign(array $data): string
    {
        uksort($data, static function ($a, $b): int {
            return strcmp(strtolower((string) $a), strtolower((string) $b));
        });

        $signatureString = '';
        foreach ($data as $key => $value) {
            $signatureString .= $key . '=' . html_entity_decode((string) $value);
        }

        return sha1($signatureString . trim(self::SECRET_KEY));
    }
}
