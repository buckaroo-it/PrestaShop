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

use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReporter;
use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReportingConsent;
use Buckaroo\PrestaShop\Src\ErrorReporting\ReportBuilder;
use Buckaroo\PrestaShop\Src\ErrorReporting\ShopMetadataProvider;
use Buckaroo\PrestaShop\Src\ErrorReporting\SuppressionStoreInterface;
use Buckaroo\PrestaShop\Src\ErrorReporting\TransportInterface;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

/**
 * Shop metadata of error reports, the shared reporter instance and shutdown flush.
 */
class ErrorReportingTest extends TestCase
{
    protected function tearDown(): void
    {
        ErrorReporter::setInstance(null);
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // ShopMetadataProvider
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public function moduleVersionProvider(): array
    {
        return [
            'module instance version' => ['5.9.1', '5.9.1'],
            'module without version' => ['', 'unknown'],
            'no module' => [null, 'unknown'],
        ];
    }

    /**
     * @dataProvider moduleVersionProvider
     */
    public function testPluginVersion($moduleVersion, string $expected): void
    {
        if ($moduleVersion !== null) {
            $module = new \Module();
            $module->version = $moduleVersion;
            \Module::$instances['buckaroo3'] = $module;
        }

        // When another test loaded the module main class, its constant wins.
        if (class_exists('Buckaroo3', false)) {
            $expected = \Buckaroo3::MODULE_VERSION;
        }

        self::assertSame($expected, (new ShopMetadataProvider())->getPluginVersion());
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public function methodModeProvider(): array
    {
        return [
            'live method' => ['{"mode":"live"}', 'live'],
            'test method' => ['{"mode":"test"}', 'test'],
            'disabled method' => ['{"mode":"off"}', 'test'],
            'unknown method (lookup throws)' => [false, 'test'],
        ];
    }

    /**
     * @dataProvider methodModeProvider
     */
    public function testEnvironmentFollowsTheModeOfTheFailingPaymentMethod($config, string $expected): void
    {
        Stubs::db('/FROM `ps_bk_payment_methods`/', '4');
        Stubs::db('/FROM `ps_bk_configuration`/', $config);

        self::assertSame($expected, (new ShopMetadataProvider())->getEnvironment('ideal'));
        self::assertStringContainsString('name = "ideal"', Stubs::sql('getValue')[0]);
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public function liveCountProvider(): array
    {
        return [
            'a live method' => ['2', 'live'],
            'no live method' => ['0', 'test'],
            'query failed' => [false, 'test'],
        ];
    }

    /**
     * @dataProvider liveCountProvider
     */
    public function testEnvironmentIsLiveWhenAnyMethodIsLive($count, string $expected): void
    {
        Stubs::db('/COUNT/', $count);

        foreach ([null, ''] as $method) {
            self::assertSame($expected, (new ShopMetadataProvider())->getEnvironment($method));
        }
        self::assertSame(
            "SELECT COUNT(*) FROM `ps_bk_configuration` WHERE `value` LIKE '%\"mode\":\"live\"%'",
            Stubs::sql('getValue')[0]
        );
    }

    public function testEnvironmentDefaultsToTestWhenTheDatabaseFails(): void
    {
        Stubs::db('/COUNT/', function () {
            throw new \RuntimeException('no db');
        });

        self::assertSame('test', (new ShopMetadataProvider())->getEnvironment());
    }

    public function testShopUrlComesFromTheModuleContextShop(): void
    {
        \Module::$instances['buckaroo3'] = new \Module();

        self::assertSame('https://shop.test/', (new ShopMetadataProvider())->getShopUrl());
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public function shopFallbackProvider(): array
    {
        return [
            'no module' => [false],
            'shop without base url' => [true],
        ];
    }

    /**
     * @dataProvider shopFallbackProvider
     */
    public function testShopUrlFallsBackToTheShopDomain(bool $withModule): void
    {
        if ($withModule) {
            $context = new \Context();
            $context->shop = new class {
                public function getBaseURL($secure = false)
                {
                    return '';
                }
            };
            \Module::$instances['buckaroo3'] = new \Module(null, $context);
        }

        self::assertSame('https://shop.test', (new ShopMetadataProvider())->getShopUrl());
    }

    public function testShopUrlIsEmptyWhenTheShopThrows(): void
    {
        $context = new \Context();
        $context->shop = new class {
            public function getBaseURL($secure = false)
            {
                throw new \RuntimeException('no shop');
            }
        };
        \Module::$instances['buckaroo3'] = new \Module(null, $context);

        self::assertSame('', (new ShopMetadataProvider())->getShopUrl());
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public function websiteKeyProvider(): array
    {
        return [
            'configured key is trimmed' => ['  ABC123 ', true],
            'no key' => [false, false],
            'blank key' => ['   ', false],
        ];
    }

    /**
     * @dataProvider websiteKeyProvider
     */
    public function testMetadataIncludesTheWebsiteKeyOnlyWhenConfigured($key, bool $included): void
    {
        if ($key !== false) {
            \Configuration::updateValue(Config::BUCKAROO_MERCHANT_KEY, $key);
        }
        Stubs::db('/COUNT/', '1');
        \Module::$instances['buckaroo3'] = new \Module();

        $metadata = (new ShopMetadataProvider())->getMetadata();

        self::assertSame('live', $metadata['environment']);
        self::assertSame(_PS_VERSION_, $metadata['platform_version']);
        self::assertSame(PHP_VERSION, $metadata['php_version']);
        self::assertSame('https://shop.test/', $metadata['shop_url']);
        self::assertNotSame('', $metadata['plugin_version']);
        if ($included) {
            self::assertSame('ABC123', $metadata['website_key']);
        } else {
            self::assertArrayNotHasKey('website_key', $metadata);
        }
    }

    // ------------------------------------------------------------------
    // ErrorReporter: shared instance and shutdown flush
    // ------------------------------------------------------------------

    private static function recordingTransport(): TransportInterface
    {
        return new class implements TransportInterface {
            /** @var array */
            public $requests = [];

            public function send(string $url, array $headers, string $body): void
            {
                $this->requests[] = [$url, $headers, json_decode($body, true)];
            }
        };
    }

    private static function memoryStore(): SuppressionStoreInterface
    {
        return new class implements SuppressionStoreInterface {
            /** @var array<string, int> */
            public $sent = [];

            public function wasSentSince(string $fingerprint, int $notBefore): bool
            {
                return isset($this->sent[$fingerprint]) && $this->sent[$fingerprint] >= $notBefore;
            }

            public function recordSent(array $fingerprints, int $now, int $notBefore): void
            {
                foreach ($fingerprints as $fingerprint) {
                    $this->sent[$fingerprint] = $now;
                }
            }
        };
    }

    private static function metadata(): ShopMetadataProvider
    {
        return new class extends ShopMetadataProvider {
            public function getMetadata(?string $paymentMethod = null): array
            {
                return ['plugin_version' => '1.2.3', 'environment' => 'test', 'shop_url' => 'https://shop.test/'];
            }

            public function getPluginVersion(): string
            {
                return '1.2.3';
            }
        };
    }

    public function testGetInstanceCreatesOneSharedReporter(): void
    {
        ErrorReporter::setInstance(null);

        $instance = ErrorReporter::getInstance();

        self::assertInstanceOf(ErrorReporter::class, $instance);
        self::assertSame($instance, ErrorReporter::getInstance());
    }

    public function testCaptureExceptionWithoutConsentBuffersNothing(): void
    {
        ErrorReporter::setInstance(null);
        \Configuration::updateValue(ErrorReportingConsent::CONFIG_KEY, ErrorReportingConsent::NO);

        ErrorReporter::captureException(new \RuntimeException('boom'));

        self::assertSame([], ErrorReporter::getInstance()->getBufferedReports());
    }

    public function testCaptureExceptionGoesToTheSharedInstance(): void
    {
        $reporter = new ErrorReporter(function () {
            return true;
        }, self::metadata(), self::recordingTransport(), 'https://collector.test', false, self::memoryStore());
        ErrorReporter::setInstance($reporter);

        ErrorReporter::captureException(new \RuntimeException('boom'), 'critical', 'Payment failed', ['payment_method' => 'ideal']);

        $reports = $reporter->getBufferedReports();
        self::assertCount(1, $reports);
        self::assertSame('critical', $reports[0]['level']);
        self::assertSame('Payment failed', $reports[0]['message']);
    }

    public function testFlushAtShutdownSendsTheBufferedReports(): void
    {
        $transport = self::recordingTransport();
        $reporter = new ErrorReporter(function () {
            return true;
        }, self::metadata(), $transport, 'https://collector.test/api', true, self::memoryStore());

        $reporter->flushAtShutdown();
        self::assertSame([], $transport->requests, 'nothing buffered, nothing sent');

        $reporter->report(new \RuntimeException('first'));
        $reporter->report(new \LogicException('second'));
        $reporter->flushAtShutdown();

        self::assertCount(1, $transport->requests);
        [$url, $headers, $payload] = $transport->requests[0];
        self::assertSame('https://collector.test/api', $url);
        self::assertSame('buckaroo-prestashop/1.2.3', $headers['User-Agent']);
        self::assertSame(ErrorReporter::BUCKAROO_PLUGIN_LOG_INGEST_KEY, $headers['X-Buckaroo-Ingest-Key']);
        self::assertSame(['RuntimeException', 'LogicException'], array_map(function ($report) {
            return $report['exception']['class'];
        }, $payload['reports']));
        self::assertSame([], $reporter->getBufferedReports());

        // The shutdown flush is registered once per reporter.
        self::assertTrue(self::getProperty($reporter, 'shutdownRegistered'));
    }

    /**
     * Development shops point the collector elsewhere with a constant.
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testEndpointConstantOverridesTheCollectorUrl(): void
    {
        if (defined('BUCKAROO_PLUGIN_LOG_ENDPOINT')) {
            self::markTestSkipped('endpoint constant already defined');
        }
        define('BUCKAROO_PLUGIN_LOG_ENDPOINT', 'https://collector.staging.test/api');

        self::assertSame('https://collector.staging.test/api', ErrorReporter::resolveEndpoint());
    }

    // ------------------------------------------------------------------
    // ReportBuilder context limits
    // ------------------------------------------------------------------

    public function testReportContextIsFlattenedAndLimited(): void
    {
        $context = ['paid' => true, 'refunded' => false, 'note' => str_repeat('a', 300)];
        for ($i = 0; $i < 30; ++$i) {
            $context['key' . $i] = $i;
        }

        $report = (new ReportBuilder())->build(new \RuntimeException('x'), 'error', '', $context);

        self::assertCount(ReportBuilder::MAX_CONTEXT_KEYS, $report['context']);
        self::assertSame('true', $report['context']['paid']);
        self::assertSame('false', $report['context']['refunded']);
        self::assertSame(ReportBuilder::MAX_CONTEXT_VALUE_LENGTH, strlen($report['context']['note']));
        self::assertSame('16', $report['context']['key16']);
        self::assertArrayNotHasKey('key17', $report['context']);
    }
}
