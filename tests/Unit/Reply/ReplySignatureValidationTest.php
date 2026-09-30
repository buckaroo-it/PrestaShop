<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Reply;

use Buckaroo\BuckarooClient;
use Buckaroo\Handlers\Reply\ReplyHandler;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the reply signature handling used by the return controller.
 */
class ReplySignatureValidationTest extends TestCase
{
    private const WEBSITE_KEY = 'TESTWEBSITEKEY';
    private const SECRET_KEY = 'a-test-secret-key-not-a-real-one';

    /**
     * @return array<string,string>
     */
    private function refundPush(): array
    {
        return [
            'brq_amount_credit' => '26.62',
            'brq_currency' => 'EUR',
            'brq_invoicenumber' => '300000123',
            'brq_ordernumber' => '300000123',
            'brq_relatedtransaction_refund' => 'A1B2C3D4E5F6A1B2C3D4E5F6A1B2C3D4',
            'brq_statuscode' => '190',
            'brq_transaction_method' => 'ideal',
            'brq_transaction_type' => 'C102',
            'brq_transactions' => 'F6E5D4C3B2A1F6E5D4C3B2A1F6E5D4C3',
        ];
    }

    /**
     * Same signing scheme as the SDK reply handler, so fixtures are not pinned to a precomputed hash.
     *
     * @param array<string,string> $data
     */
    private function sign(array $data): string
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

        return sha1($signatureString . trim(self::SECRET_KEY));
    }

    /**
     * @param array<string,string> $data
     */
    private function isValid(array $data): bool
    {
        $buckaroo = new BuckarooClient(self::WEBSITE_KEY, self::SECRET_KEY);
        $handler = new ReplyHandler($buckaroo->client()->config(), $data);
        $handler->validate();

        return $handler->isValid();
    }

    public function testSignedReplyIsValid(): void
    {
        $push = $this->refundPush();
        $push['brq_signature'] = $this->sign($push);

        $this->assertTrue($this->isValid($push));
    }

    public function testModifiedReplyIsInvalid(): void
    {
        $push = $this->refundPush();
        $push['brq_signature'] = $this->sign($push);
        $push['brq_amount_credit'] = '9999.99';

        $this->assertFalse($this->isValid($push));
    }

    public function testReplyWithoutSignatureIsInvalid(): void
    {
        $this->assertFalse($this->isValid($this->refundPush()));
    }

    public function testReplyWithDuplicateKeyIsInvalid(): void
    {
        $push = $this->refundPush();
        $push['brq_signature'] = $this->sign($push);
        $push['Brq_statuscode'] = '490';

        $this->assertFalse($this->isValid($push));
    }
}
