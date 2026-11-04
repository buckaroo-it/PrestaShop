<?php

declare(strict_types=1);

use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReporter;
use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReportingConsent;
use Buckaroo\PrestaShop\Src\ErrorReporting\Scrubber;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ErrorReportingTestDoubles.php';

class ScrubberTest extends TestCase
{
    /**
     * @dataProvider sensitiveStringProvider
     */
    public function testSensitiveValuesAreMasked(string $input, string $mustNotContain, string $mask): void
    {
        $result = Scrubber::scrubString($input);

        $this->assertStringNotContainsString($mustNotContain, $result);
        $this->assertStringContainsString($mask, $result);
    }

    public static function sensitiveStringProvider(): array
    {
        return [
            'card number' => ['Card 4111111111111111 declined', '4111111111111111', Scrubber::MASK_CARD],
            'card number with spaces' => ['Card 4111 1111 1111 1111 declined', '4111 1111 1111 1111', Scrubber::MASK_CARD],
            'card number with dashes' => ['Card 5500-0000-0000-0004 declined', '5500-0000-0000-0004', Scrubber::MASK_CARD],
            '19 digit card' => ['PAN 6304000000000000000', '6304000000000000000', Scrubber::MASK_CARD],
            'email' => ['Customer john.doe+test@example.co.uk not found', 'john.doe+test@example.co.uk', Scrubber::MASK_EMAIL],
            'iban' => ['Invalid IBAN NL91ABNA0417164300 given', 'NL91ABNA0417164300', Scrubber::MASK_IBAN],
            'iban grouped' => ['IBAN DE89 3704 0044 0532 0130 00 rejected', '3704 0044 0532', Scrubber::MASK_IBAN],
            'password pair' => ['login failed password=hunter2 for user', 'hunter2', Scrubber::MASK],
            'json secret' => ['{"secret_key":"s3cr3tValue"}', 's3cr3tValue', Scrubber::MASK],
            'token pair' => ['token: abc.def.ghi', 'abc.def.ghi', Scrubber::MASK],
            'api key pair' => ['api_key=XYZ987', 'XYZ987', Scrubber::MASK],
        ];
    }

    public function testLongNumbersThatAreNotCardsAreKept(): void
    {
        $input = 'Order 1000001234567 at 1730716800000';

        $this->assertSame($input, Scrubber::scrubString($input));
    }

    public function testHarmlessTextIsKept(): void
    {
        $input = 'Order 100000123 failed in /modules/buckaroo3/api/paymentmethods/response.php on line 220';

        $this->assertSame($input, Scrubber::scrubString($input));
    }

    public function testArrayScrubbingMasksSensitiveKeysRecursively(): void
    {
        $result = Scrubber::scrubArray([
            'password' => 'hunter2',
            'nested' => ['api_key' => 'XYZ', 'Authorization' => 'Bearer abc', 'note' => 'mail me at a@b.com'],
            'amount' => 10,
        ]);

        $this->assertSame(Scrubber::MASK, $result['password']);
        $this->assertSame(Scrubber::MASK, $result['nested']['api_key']);
        $this->assertSame(Scrubber::MASK, $result['nested']['Authorization']);
        $this->assertSame('mail me at ' . Scrubber::MASK_EMAIL, $result['nested']['note']);
        $this->assertSame(10, $result['amount']);
    }

    public function testReportsAreScrubbedBeforeSending(): void
    {
        Configuration::$values = [ErrorReportingConsent::CONFIG_KEY => '1'];
        $transport = new RecordingTransport();
        $reporter = new ErrorReporter(null, new FixedMetadataProvider(), $transport, ErrorReporter::ENDPOINT, false);

        $previous = new RuntimeException('IBAN NL91ABNA0417164300 rejected');
        $reporter->report(
            new RuntimeException('Card 4111111111111111 of jane@example.com failed', 0, $previous),
            'error',
            'Checkout failed for jane@example.com with password=hunter2',
            ['payment_method' => 'creditcard', 'secret_key' => 'abc', 'note' => 'jane@example.com', 'nested' => ['x']]
        );
        $reporter->flush();

        $body = $transport->requests[0]['body'];
        foreach (['4111111111111111', 'jane@example.com', 'NL91ABNA0417164300', 'hunter2'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }

        $report = $transport->lastPayload()['reports'][0];
        $this->assertSame(Scrubber::MASK, $report['context']['secret_key']);
        $this->assertSame(Scrubber::MASK_EMAIL, $report['context']['note']);
        $this->assertArrayNotHasKey('nested', $report['context']);
        Configuration::$values = [];
    }
}
