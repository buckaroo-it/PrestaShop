<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Reply;

use Buckaroo\Transaction\Response\TransactionResponse;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../api/paymentmethods/responsefactory.php';

/**
 * A Response built from a direct transaction response (no push data) must carry
 * the same public fields a push fills, so request.php can act on them.
 */
class DirectResponseTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    public function testSuccessfulReplyFillsFields(): void
    {
        $response = $this->buildResponse([
            'Key' => 'TXKEY190',
            'Invoice' => 'ABCDEFGHI_42',
            'AmountDebit' => 12.5,
            'Currency' => 'EUR',
            'ServiceCode' => 'creditcard',
            'Services' => [['Name' => 'visa', 'Action' => 'Pay', 'Parameters' => []]],
            'Status' => [
                'Code' => ['Code' => 190, 'Description' => 'Success'],
                'SubCode' => ['Code' => 'S990', 'Description' => 'The request was successful.'],
            ],
        ]);

        $this->assertInstanceOf(\ResponseDefault::class, $response);
        $this->assertSame('visa', $response->payment_method);
        $this->assertSame(\BuckarooAbstract::BUCKAROO_SUCCESS, $response->status);
        $this->assertSame('Success', $response->message);
        $this->assertSame('The request was successful.', $response->statusmessage);
        $this->assertSame('ABCDEFGHI_42', $response->invoice);
        $this->assertSame('ABCDEFGHI_42', $response->invoicenumber);
        $this->assertSame('TXKEY190', $response->transactions);
        $this->assertSame('12.5', $response->amount);
        $this->assertSame('EUR', $response->currency);
        $this->assertSame(42, $response->getCartId());
        $this->assertSame('ABCDEFGHI', $response->getReferenceId());
        $this->assertTrue($response->hasSucceeded());
        $this->assertTrue($response->isPaid());
    }

    public function testFailedReplyKeepsBuckarooStatusMessage(): void
    {
        $response = $this->buildResponse([
            'Key' => 'TXKEY490',
            'Invoice' => 'REFERENCE_7',
            'AmountDebit' => 10,
            'Currency' => 'EUR',
            'ServiceCode' => 'ideal',
            'Status' => [
                'Code' => ['Code' => 490, 'Description' => 'Failed'],
                'SubCode' => ['Code' => 'S103', 'Description' => 'The transaction was declined by the bank.'],
            ],
        ]);

        $this->assertSame('ideal', $response->payment_method);
        $this->assertSame(\BuckarooAbstract::BUCKAROO_FAILED, $response->status);
        $this->assertSame('The transaction was declined by the bank.', $response->statusmessage);
        $this->assertSame(7, $response->getCartId());
        $this->assertFalse($response->hasSucceeded());
    }

    public function testStatusMessageFallsBackToCodeDescription(): void
    {
        $response = $this->buildResponse([
            'Invoice' => 'REFERENCE_7',
            'ServiceCode' => 'ideal',
            'Status' => ['Code' => ['Code' => 690, 'Description' => 'Rejected']],
        ]);

        $this->assertSame('Rejected', $response->statusmessage);
        $this->assertSame(\BuckarooAbstract::BUCKAROO_FAILED, $response->status);
    }

    public function testPendingReplyMapsToPendingStatus(): void
    {
        $response = $this->buildResponse([
            'Key' => 'TXKEY791',
            'Invoice' => 'PENDING_9',
            'AmountDebit' => 5,
            'Currency' => 'EUR',
            'ServiceCode' => 'paybybank',
            'Status' => ['Code' => ['Code' => 791, 'Description' => 'Pending processing']],
        ]);

        $this->assertSame(\BuckarooAbstract::BUCKAROO_PENDING_PAYMENT, $response->status);
        $this->assertTrue($response->hasSucceeded());
        $this->assertFalse($response->isPaid());
    }

    public function testSepaDirectDebitReply(): void
    {
        $response = $this->buildResponse([
            'Key' => 'TXKEYSEPA',
            'Invoice' => 'SEPAREF_11',
            'AmountDebit' => 20,
            'Currency' => 'EUR',
            'ServiceCode' => 'sepadirectdebit',
            'Services' => [[
                'Name' => 'SepaDirectDebit',
                'Action' => null,
                'Parameters' => [
                    ['Name' => 'MandateReference', 'Value' => '001R123'],
                    ['Name' => 'MandateDate', 'Value' => '2026-11-18'],
                ],
            ]],
            'Status' => ['Code' => ['Code' => 791, 'Description' => 'Pending processing']],
        ]);

        // Services[0].Name wins over ServiceCode.
        $this->assertSame('SepaDirectDebit', $response->payment_method);
        $this->assertSame(\BuckarooAbstract::BUCKAROO_PENDING_PAYMENT, $response->status);
        $this->assertSame(11, $response->getCartId());
        $this->assertSame('TXKEYSEPA', $response->transactions);

        // request.php reads these lower-case keys for the order messages.
        $parameters = $response->getServiceParameters();
        $this->assertSame('001R123', $parameters['mandatereference']);
        $this->assertSame('2026-11-18', $parameters['mandatedate']);
    }

    public function testTransferReplyKeepsConsumerMessage(): void
    {
        $response = $this->buildResponse([
            'Key' => 'TXKEYTRANSFER',
            'Invoice' => 'TRANSFER_12',
            'AmountDebit' => 30,
            'Currency' => 'EUR',
            'ServiceCode' => 'transfer',
            'Services' => [['Name' => 'transfer', 'Action' => null, 'Parameters' => []]],
            'ConsumerMessage' => ['MustRead' => true, 'Title' => 'Transfer', 'HtmlText' => '<p>Pay to IBAN</p>'],
            'Status' => ['Code' => ['Code' => 792, 'Description' => 'Awaiting consumer']],
        ]);

        $this->assertSame('transfer', $response->payment_method);
        $this->assertSame(\BuckarooAbstract::BUCKAROO_PENDING_PAYMENT, $response->status);
        $this->assertSame('<p>Pay to IBAN</p>', $response->consumerMessage['HtmlText']);
        $this->assertSame(12, $response->getCartId());
    }

    public function testCapayableServiceIsReportedAsIn3(): void
    {
        $response = $this->buildResponse([
            'ServiceCode' => 'Capayable',
            'Status' => ['Code' => ['Code' => 190]],
        ]);

        $this->assertSame('in3', $response->payment_method);
    }

    public function testUnknownStatusCodeLeavesStatusEmpty(): void
    {
        $response = $this->buildResponse([
            'Invoice' => 'X_1',
            'Status' => ['Code' => ['Code' => 999]],
        ]);

        $this->assertNull($response->status);
        $this->assertNull($response->message);
    }

    public function testEmptyReplyLeavesFieldsNull(): void
    {
        $response = $this->buildResponse([]);

        $this->assertNull($response->payment_method);
        $this->assertNull($response->status);
        $this->assertNull($response->statusmessage);
        $this->assertNull($response->invoicenumber);
        $this->assertNull($response->transactions);
        $this->assertNull($response->consumerMessage);
        $this->assertSame(0, $response->getCartId());
    }

    public function testRedirectReplyStillRedirects(): void
    {
        $response = $this->buildResponse([
            'Key' => 'TXKEYREDIRECT',
            'Invoice' => 'REDIRECT_13',
            'AmountDebit' => 40,
            'Currency' => 'EUR',
            'ServiceCode' => 'ideal',
            'RequiredAction' => ['Name' => 'Redirect', 'RedirectURL' => 'https://checkout.buckaroo.nl/x'],
            'Status' => ['Code' => ['Code' => 791, 'Description' => 'Pending processing']],
        ]);

        $this->assertTrue($response->isRedirectRequired());
        $this->assertSame('https://checkout.buckaroo.nl/x', $response->getRedirectUrl());
        $this->assertSame(13, $response->getCartId());
    }

    public function testPushRequestStillParsedFromRequestData(): void
    {
        $_POST = [
            'brq_statuscode' => '190',
            'brq_statusmessage' => 'Transaction successfully processed',
            'brq_payment_method' => 'ideal',
            'brq_invoicenumber' => 'PUSHREF_5',
            'brq_amount' => '15.00',
            'brq_currency' => 'EUR',
            'brq_transactions' => 'PUSHKEY',
        ];

        $response = \ResponseFactory::getResponse();

        $this->assertNull($response->getResponse());
        $this->assertSame('ideal', $response->payment_method);
        $this->assertSame(\BuckarooAbstract::BUCKAROO_SUCCESS, $response->status);
        $this->assertSame('Transaction successfully processed', $response->statusmessage);
        $this->assertSame(5, $response->getCartId());
        $this->assertSame('PUSHKEY', $response->transactions);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function buildResponse(array $data): \Response
    {
        return \ResponseFactory::getResponse(new TransactionResponse(null, $data));
    }
}
