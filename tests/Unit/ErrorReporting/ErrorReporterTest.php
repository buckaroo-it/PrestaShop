<?php

declare(strict_types=1);

use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReporter;
use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReportingConsent;
use Buckaroo\PrestaShop\Src\ErrorReporting\TransportInterface;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ErrorReportingTestDoubles.php';

class ErrorReporterTest extends TestCase
{
    protected function setUp(): void
    {
        Configuration::$values = [];
        ErrorReporter::setInstance(null);
    }

    protected function tearDown(): void
    {
        Configuration::$values = [];
        ErrorReporter::setInstance(null);
    }

    private function makeReporter(TransportInterface $transport, ?callable $consent = null, $metadata = null): ErrorReporter
    {
        return new ErrorReporter(
            $consent ?: [ErrorReportingConsent::class, 'isGranted'],
            $metadata ?: new FixedMetadataProvider(),
            $transport,
            ErrorReporter::ENDPOINT,
            false
        );
    }

    /**
     * @dataProvider nonConsentStatesProvider
     */
    public function testNothingIsSentWithoutExplicitConsent($state): void
    {
        if ($state !== null) {
            Configuration::$values[ErrorReportingConsent::CONFIG_KEY] = $state;
        }
        $transport = new RecordingTransport();
        $reporter = $this->makeReporter($transport);

        $reporter->report(new RuntimeException('boom'));
        $reporter->flush();

        $this->assertSame([], $reporter->getBufferedReports());
        $this->assertSame([], $transport->requests);
    }

    public static function nonConsentStatesProvider(): array
    {
        return [
            'never stored' => [null],
            'not asked yet' => [''],
            'declined' => ['0'],
            'garbage' => ['yes'],
        ];
    }

    public function testConsentIsRecheckedBeforeSending(): void
    {
        Configuration::$values[ErrorReportingConsent::CONFIG_KEY] = '1';
        $transport = new RecordingTransport();
        $reporter = $this->makeReporter($transport);

        $reporter->report(new RuntimeException('boom'));
        Configuration::$values[ErrorReportingConsent::CONFIG_KEY] = '0';
        $reporter->flush();

        $this->assertSame([], $transport->requests);
    }

    public function testPayloadMatchesCollectorContract(): void
    {
        Configuration::$values[ErrorReportingConsent::CONFIG_KEY] = '1';
        $transport = new RecordingTransport();
        $metadata = new FixedMetadataProvider();
        $reporter = $this->makeReporter($transport, null, $metadata);

        $previous = new InvalidArgumentException('inner', 5);
        $reporter->report(
            new RuntimeException('outer', 42, $previous),
            'critical',
            'Unexpected checkout exception',
            ['payment_method' => 'ideal', 'action' => 'request', 'order_id' => 100000123]
        );
        $reporter->flush();

        $this->assertCount(1, $transport->requests);
        $request = $transport->requests[0];
        $this->assertSame('https://plugin-logs.buckaroo.io/api/v1/reports', $request['url']);
        $this->assertSame('application/json', $request['headers']['Content-Type']);
        $this->assertSame('public-plugin-ingest-key', $request['headers']['X-Buckaroo-Ingest-Key']);
        $this->assertSame('buckaroo-prestashop/5.3.0', $request['headers']['User-Agent']);
        $this->assertSame('ideal', $metadata->requestedPaymentMethod);

        $payload = $transport->lastPayload();
        $this->assertSame('prestashop', $payload['platform']);
        $this->assertSame('5.3.0', $payload['plugin_version']);
        $this->assertSame('9.0.1.0', $payload['platform_version']);
        $this->assertSame('8.2.12', $payload['php_version']);
        $this->assertSame('live', $payload['environment']);
        $this->assertSame('https://shop.example.com/', $payload['shop_url']);
        $this->assertSame('ABC123', $payload['website_key']);
        $this->assertCount(1, $payload['reports']);

        $report = $payload['reports'][0];
        $this->assertSame('critical', $report['level']);
        $this->assertSame('Unexpected checkout exception', $report['message']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $report['occurred_at']);
        $this->assertSame(['payment_method' => 'ideal', 'action' => 'request', 'order_id' => '100000123'], $report['context']);

        $exception = $report['exception'];
        $this->assertSame('RuntimeException', $exception['class']);
        $this->assertSame('outer', $exception['message']);
        $this->assertSame('42', $exception['code']);
        $this->assertSame(__FILE__, $exception['file']);
        $this->assertIsInt($exception['line']);
        $this->assertNotEmpty($exception['trace']);
        $this->assertLessThanOrEqual(50, count($exception['trace']));
        foreach ($exception['trace'] as $frame) {
            $this->assertArrayNotHasKey('args', $frame);
            $this->assertArrayHasKey('function', $frame);
        }
        $this->assertSame('InvalidArgumentException', $exception['previous'][0]['class']);
        $this->assertSame('inner', $exception['previous'][0]['message']);
        $this->assertSame('5', $exception['previous'][0]['code']);
    }

    public function testTraceArgumentsAreNeverSent(): void
    {
        Configuration::$values[ErrorReportingConsent::CONFIG_KEY] = '1';
        $transport = new RecordingTransport();
        $reporter = $this->makeReporter($transport);

        try {
            $this->throwWithSecretArgument('4111111111111111', 'john@example.com');
        } catch (Throwable $e) {
            $reporter->report($e);
        }
        $reporter->flush();

        $body = $transport->requests[0]['body'];
        $this->assertStringNotContainsString('4111111111111111', $body);
        $this->assertStringNotContainsString('john@example.com', $body);
        $this->assertStringNotContainsString('"args"', $body);
    }

    private function throwWithSecretArgument(string $card, string $email): void
    {
        throw new RuntimeException('Payment failed');
    }

    public function testIdenticalExceptionsAreDedupedWithinOneRequest(): void
    {
        Configuration::$values[ErrorReportingConsent::CONFIG_KEY] = '1';
        $transport = new RecordingTransport();
        $reporter = $this->makeReporter($transport);

        $exception = new RuntimeException('same');
        $reporter->report($exception);
        $reporter->report($exception);
        $reporter->report(new RuntimeException('other'));
        $reporter->flush();

        $this->assertCount(1, $transport->requests);
        $this->assertCount(2, $transport->lastPayload()['reports']);
    }

    public function testAtMostTwentyReportsPerRequest(): void
    {
        Configuration::$values[ErrorReportingConsent::CONFIG_KEY] = '1';
        $transport = new RecordingTransport();
        $reporter = $this->makeReporter($transport);

        for ($i = 0; $i < 30; ++$i) {
            $reporter->report(new RuntimeException('error ' . $i));
        }
        $reporter->flush();

        $this->assertCount(1, $transport->requests);
        $this->assertCount(20, $transport->lastPayload()['reports']);
    }

    public function testFlushClearsBufferAndSendsOnlyOnce(): void
    {
        Configuration::$values[ErrorReportingConsent::CONFIG_KEY] = '1';
        $transport = new RecordingTransport();
        $reporter = $this->makeReporter($transport);

        $reporter->report(new RuntimeException('boom'));
        $reporter->flush();
        $reporter->flush();

        $this->assertCount(1, $transport->requests);
        $this->assertSame([], $reporter->getBufferedReports());
    }

    public function testTransportFailuresAreSwallowed(): void
    {
        Configuration::$values[ErrorReportingConsent::CONFIG_KEY] = '1';
        $transport = new FailingTransport();
        $reporter = $this->makeReporter($transport);

        $reporter->report(new RuntimeException('boom'));
        $reporter->flush();

        $this->assertSame(1, $transport->calls);
    }

    public function testMetadataFailuresAreSwallowed(): void
    {
        Configuration::$values[ErrorReportingConsent::CONFIG_KEY] = '1';
        $transport = new RecordingTransport();
        $reporter = $this->makeReporter($transport, null, new ThrowingMetadataProvider());

        $reporter->report(new RuntimeException('boom'));
        $reporter->flush();

        $this->assertSame([], $transport->requests);
    }

    public function testConsentCheckerFailureMeansNoConsent(): void
    {
        $transport = new RecordingTransport();
        $reporter = $this->makeReporter($transport, function () {
            throw new RuntimeException('Configuration unavailable');
        });

        $reporter->report(new RuntimeException('boom'));
        $reporter->flush();

        $this->assertSame([], $reporter->getBufferedReports());
        $this->assertSame([], $transport->requests);
    }

    public function testStaticCaptureUsesSharedInstance(): void
    {
        Configuration::$values[ErrorReportingConsent::CONFIG_KEY] = '1';
        $transport = new RecordingTransport();
        $reporter = $this->makeReporter($transport);
        ErrorReporter::setInstance($reporter);

        ErrorReporter::captureException(new RuntimeException('boom'), 'warning');

        $this->assertCount(1, $reporter->getBufferedReports());
        $this->assertSame('warning', $reporter->getBufferedReports()[0]['level']);
    }

    public function testUnknownLevelFallsBackToError(): void
    {
        Configuration::$values[ErrorReportingConsent::CONFIG_KEY] = '1';
        $reporter = $this->makeReporter(new RecordingTransport());

        $reporter->report(new RuntimeException('boom'), 'debug');

        $this->assertSame('error', $reporter->getBufferedReports()[0]['level']);
    }

    public function testEndpointDefaultsToTheCollector(): void
    {
        putenv('BUCKAROO_PLUGIN_LOG_ENDPOINT');

        $this->assertSame(ErrorReporter::ENDPOINT, ErrorReporter::resolveEndpoint());
    }

    public function testEndpointCanBeOverriddenFromTheEnvironment(): void
    {
        putenv('BUCKAROO_PLUGIN_LOG_ENDPOINT=http://collector.test/api/v1/reports');

        try {
            $this->assertSame('http://collector.test/api/v1/reports', ErrorReporter::resolveEndpoint());
        } finally {
            putenv('BUCKAROO_PLUGIN_LOG_ENDPOINT');
        }
    }
}
