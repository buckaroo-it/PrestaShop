<?php

declare(strict_types=1);

use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReporter;
use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReportingConsent;
use Buckaroo\PrestaShop\Src\ErrorReporting\FileSuppressionStore;
use Buckaroo\PrestaShop\Src\ErrorReporting\SuppressionStoreInterface;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ErrorReportingTestDoubles.php';

/**
 * The same exception is sent at most once per 15 minutes across requests.
 * Each new ErrorReporter instance stands for a new request.
 */
class ErrorReporterSuppressionTest extends TestCase
{
    /** @var RecordingTransport */
    private $transport;

    /** @var FakeClock */
    private $clock;

    /** @var string */
    private $tmpDir;

    protected function setUp(): void
    {
        \Configuration::$values = [ErrorReportingConsent::CONFIG_KEY => '1'];
        $this->transport = new RecordingTransport();
        $this->clock = new FakeClock();
        $this->tmpDir = sys_get_temp_dir() . '/bk_suppression_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        \Configuration::$values = [];
        if (is_dir($this->tmpDir)) {
            @chmod($this->tmpDir, 0755);
            foreach ((array) glob($this->tmpDir . '/*') as $file) {
                @unlink((string) $file);
            }
            @rmdir($this->tmpDir);
        }
    }

    private function request(SuppressionStoreInterface $store): ErrorReporter
    {
        return new ErrorReporter(null, new FixedMetadataProvider(), $this->transport, ErrorReporter::ENDPOINT, false, $store, $this->clock);
    }

    /**
     * Exceptions created on one line share class, code, file and line.
     */
    private function exceptionFromSameLine(string $message): \RuntimeException
    {
        return new \RuntimeException($message);
    }

    private function sendInNewRequest(SuppressionStoreInterface $store, \Throwable $exception): void
    {
        $reporter = $this->request($store);
        $reporter->report($exception);
        $reporter->flush();
    }

    public function testWindowIsFifteenMinutes(): void
    {
        $this->assertSame(900, ErrorReporter::SUPPRESSION_WINDOW_SECONDS);
    }

    public function testSameExceptionInLaterRequestWithinWindowIsNotSent(): void
    {
        $store = new InMemorySuppressionStore();

        $this->sendInNewRequest($store, $this->exceptionFromSameLine('boom'));
        $this->clock->now += 600;
        $this->sendInNewRequest($store, $this->exceptionFromSameLine('boom'));

        $this->assertCount(1, $this->transport->requests);
    }

    public function testSameExceptionIsSentAgainAfterWindow(): void
    {
        $store = new InMemorySuppressionStore();

        $this->sendInNewRequest($store, $this->exceptionFromSameLine('boom'));
        $this->clock->now += ErrorReporter::SUPPRESSION_WINDOW_SECONDS + 1;
        $this->sendInNewRequest($store, $this->exceptionFromSameLine('boom'));

        $this->assertCount(2, $this->transport->requests);
    }

    public function testSameClassOnDifferentLineIsStillSent(): void
    {
        $store = new InMemorySuppressionStore();

        $this->sendInNewRequest($store, new \RuntimeException('boom'));
        $this->sendInNewRequest($store, new \RuntimeException('boom'));

        $this->assertCount(2, $this->transport->requests);
    }

    public function testDifferentMessageOnSameClassFileAndLineIsSuppressed(): void
    {
        $store = new InMemorySuppressionStore();

        $this->sendInNewRequest($store, $this->exceptionFromSameLine('Order 100000123 failed'));
        $this->sendInNewRequest($store, $this->exceptionFromSameLine('Order 100000124 failed'));

        $this->assertCount(1, $this->transport->requests);
    }

    public function testNothingIsRecordedWithoutConsent(): void
    {
        $store = new InMemorySuppressionStore();
        \Configuration::$values = [ErrorReportingConsent::CONFIG_KEY => '0'];

        $this->sendInNewRequest($store, $this->exceptionFromSameLine('boom'));

        $this->assertSame([], $store->entries);
        $this->assertSame([], $this->transport->requests);
    }

    public function testRecordedWhenSentEvenIfTransportFails(): void
    {
        $store = new InMemorySuppressionStore();
        $reporter = new ErrorReporter(null, new FixedMetadataProvider(), new FailingTransport(), ErrorReporter::ENDPOINT, false, $store, $this->clock);

        $reporter->report($this->exceptionFromSameLine('boom'));
        $this->assertSame([], $store->entries, 'nothing is recorded before the flush');
        $reporter->flush();

        $this->assertCount(1, $store->entries);
    }

    public function testFileStoreSuppressesAcrossRequests(): void
    {
        $path = $this->tmpDir . '/error_reporting.json';

        $this->sendInNewRequest(new FileSuppressionStore($path), $this->exceptionFromSameLine('boom'));
        $this->sendInNewRequest(new FileSuppressionStore($path), $this->exceptionFromSameLine('boom'));

        $this->assertCount(1, $this->transport->requests);
        $stored = json_decode((string) file_get_contents($path), true);
        $this->assertCount(1, $stored);
        $this->assertSame($this->clock->now, reset($stored));
    }

    public function testFileStorePrunesExpiredAndCapsEntries(): void
    {
        $path = $this->tmpDir . '/error_reporting.json';
        mkdir($this->tmpDir, 0755, true);
        $entries = ['expired' => $this->clock->now - 10000];
        for ($i = 0; $i < 600; ++$i) {
            $entries['fp' . $i] = $this->clock->now - $i;
        }
        file_put_contents($path, json_encode($entries));

        (new FileSuppressionStore($path))->recordSent(['new'], $this->clock->now, $this->clock->now - 900);

        $stored = json_decode((string) file_get_contents($path), true);
        $this->assertArrayNotHasKey('expired', $stored);
        $this->assertArrayHasKey('new', $stored);
        $this->assertCount(FileSuppressionStore::MAX_ENTRIES, $stored);
    }

    public function testCorruptStoreIsIgnoredAndReportIsSent(): void
    {
        $path = $this->tmpDir . '/error_reporting.json';
        mkdir($this->tmpDir, 0755, true);
        file_put_contents($path, '{not json');

        $this->sendInNewRequest(new FileSuppressionStore($path), $this->exceptionFromSameLine('boom'));

        $this->assertCount(1, $this->transport->requests);
    }

    public function testUnwritableStoreIsIgnoredAndReportIsSent(): void
    {
        mkdir($this->tmpDir, 0555, true);
        if (is_writable($this->tmpDir)) {
            $this->markTestSkipped('Cannot create an unwritable directory (running as root?)');
        }
        $path = $this->tmpDir . '/sub/error_reporting.json';

        $this->sendInNewRequest(new FileSuppressionStore($path), $this->exceptionFromSameLine('boom'));
        $this->sendInNewRequest(new FileSuppressionStore($path), $this->exceptionFromSameLine('boom'));

        // Nothing could be stored, so nothing is suppressed: both are sent.
        $this->assertCount(2, $this->transport->requests);
    }

    public function testThrowingStoreIsIgnoredAndReportIsSent(): void
    {
        $store = new class implements SuppressionStoreInterface {
            public function wasSentSince(string $fingerprint, int $notBefore): bool
            {
                throw new \RuntimeException('disk error');
            }

            public function recordSent(array $fingerprints, int $now, int $notBefore): void
            {
                throw new \RuntimeException('disk error');
            }
        };

        $this->sendInNewRequest($store, $this->exceptionFromSameLine('boom'));

        $this->assertCount(1, $this->transport->requests);
    }

    public function testStoreWithoutCacheDirIsANoOp(): void
    {
        $store = new FileSuppressionStore(null);
        if (defined('_PS_CACHE_DIR_')) {
            $this->markTestSkipped('_PS_CACHE_DIR_ is defined in this environment');
        }

        $store->recordSent(['fp'], 100, 0);

        $this->assertFalse($store->wasSentSince('fp', 0));
    }
}
