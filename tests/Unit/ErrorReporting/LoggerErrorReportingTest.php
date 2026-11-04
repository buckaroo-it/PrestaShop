<?php

declare(strict_types=1);

use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReporter;
use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReportingConsent;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ErrorReportingTestDoubles.php';
require_once __DIR__ . '/../../../library/logger.php';

/**
 * The plugin logger forwards exceptions it logs as error/warning to the reporter.
 */
class LoggerErrorReportingTest extends TestCase
{
    /** @var RecordingTransport */
    private $transport;

    /** @var ErrorReporter */
    private $reporter;

    protected function setUp(): void
    {
        Configuration::$values = [ErrorReportingConsent::CONFIG_KEY => '1'];
        $this->transport = new RecordingTransport();
        $this->reporter = new ErrorReporter(null, new FixedMetadataProvider(), $this->transport, ErrorReporter::ENDPOINT, false);
        ErrorReporter::setInstance($this->reporter);
    }

    protected function tearDown(): void
    {
        Configuration::$values = [];
        ErrorReporter::setInstance(null);
    }

    /**
     * A level above ERROR keeps the logger from writing log files during tests.
     */
    private function logger(): Logger
    {
        return new Logger('9', 'request');
    }

    public function testExceptionAsDescriptionIsReported(): void
    {
        $this->logger()->logError('Cart delete failed', new RuntimeException('boom'));

        $reports = $this->reporter->getBufferedReports();
        $this->assertCount(1, $reports);
        $this->assertSame('error', $reports[0]['level']);
        $this->assertSame('Cart delete failed', $reports[0]['message']);
        $this->assertSame('RuntimeException', $reports[0]['exception']['class']);
        $this->assertSame(['action' => 'request'], $reports[0]['context']);
    }

    public function testExceptionKeyInArrayIsReportedWithWhitelistedContextOnly(): void
    {
        $this->logger()->logError('Order validation failed: ', [
            'exception' => new LogicException('invalid'),
            'payment_method' => 'ideal',
            'cart_id' => 12,
            'customer_email' => 'john@example.com',
        ]);

        $report = $this->reporter->getBufferedReports()[0];
        $this->assertSame(['payment_method' => 'ideal', 'cart_id' => '12', 'action' => 'request'], $report['context']);
    }

    public function testWarningsAreReportedAsWarning(): void
    {
        $this->logger()->logWarn('Fallback used', new RuntimeException('warn'));

        $this->assertSame('warning', $this->reporter->getBufferedReports()[0]['level']);
    }

    public function testMessagesWithoutExceptionAreNotReported(): void
    {
        $logger = $this->logger();
        $logger->logError('Payment failed', ['statusMessage' => 'Declined']);
        $logger->logError('Set checkout info: ', 'some string');
        $logger->logInfo('info', new RuntimeException('info level is never reported'));

        $this->assertSame([], $this->reporter->getBufferedReports());
    }

    public function testNothingIsReportedWithoutConsent(): void
    {
        Configuration::$values = [ErrorReportingConsent::CONFIG_KEY => ''];

        $this->logger()->logError('boom', new RuntimeException('boom'));
        $this->reporter->flush();

        $this->assertSame([], $this->transport->requests);
    }
}
