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

namespace Buckaroo\PrestaShop\Tests\Unit\Logs;

use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\Logs\LogLevel;
use Buckaroo\PrestaShop\Src\Service\BuckarooSettingsService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../api/corelogger.php';
require_once __DIR__ . '/../../../upgrade/upgrade-5.5.0.php';

final class LogLevelTest extends TestCase
{
    /** @var string[] */
    private $files = [];

    protected function setUp(): void
    {
        \Configuration::$values = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        \Configuration::$values = [];
    }

    public function testDefaultIsWarning(): void
    {
        self::assertSame(LogLevel::WARNING, LogLevel::DEFAULT_LEVEL);
        self::assertSame(LogLevel::WARNING, LogLevel::configured());
    }

    public function testNormalizeAcceptsOnlyKnownLevels(): void
    {
        self::assertSame('0', LogLevel::normalize('0'));
        self::assertSame('3', LogLevel::normalize(3));
        self::assertNull(LogLevel::normalize('4'));
        self::assertNull(LogLevel::normalize('-1'));
        self::assertNull(LogLevel::normalize(''));
        self::assertNull(LogLevel::normalize(false));
        self::assertNull(LogLevel::normalize(null));
        self::assertNull(LogLevel::normalize('warning'));
        self::assertNull(LogLevel::normalize(' 1'));
    }

    public function testConfiguredReadsTheSettingAndFallsBackOnInvalidValues(): void
    {
        \Configuration::$values[Config::BUCKAROO_LOG_LEVEL] = '0';
        self::assertSame(LogLevel::DEBUG, LogLevel::configured());

        \Configuration::$values[Config::BUCKAROO_LOG_LEVEL] = 'verbose';
        self::assertSame(LogLevel::DEFAULT_LEVEL, LogLevel::configured());
    }

    public function testCoreLoggerConstantsMatchTheLevels(): void
    {
        self::assertSame(LogLevel::DEBUG, \CoreLogger::DEBUG);
        self::assertSame(LogLevel::INFO, \CoreLogger::INFO);
        self::assertSame(LogLevel::WARNING, \CoreLogger::WARN);
        self::assertSame(LogLevel::ERROR, \CoreLogger::ERROR);
    }

    public function testCoreLoggerUsesTheConfiguredLevel(): void
    {
        \Configuration::$values[Config::BUCKAROO_LOG_LEVEL] = LogLevel::INFO;
        self::assertSame(LogLevel::INFO, (new \CoreLogger('test'))->getLevel());

        \Configuration::$values = [];
        self::assertSame(LogLevel::WARNING, (new \CoreLogger('test'))->getLevel());

        self::assertSame(LogLevel::DEBUG, (new \CoreLogger('test', \CoreLogger::DEBUG))->getLevel(), 'an explicit level wins');
    }

    public function testCoreLoggerWritesOnlyEntriesAtOrAboveTheConfiguredLevel(): void
    {
        $channel = 'leveltest' . bin2hex(random_bytes(4));
        $file = \CoreLogger::logDirectory() . 'api-' . $channel . '-log-' . date('Y-m-d') . '.txt';
        $this->files[] = $file;

        $logger = new \CoreLogger($channel);
        $logger->logDebug('debug entry', ['dump' => 'large object']);
        $logger->logInfo('info entry');
        $logger->logWarn('warning entry');
        $logger->logError('error entry');

        $content = (string) file_get_contents($file);
        self::assertStringNotContainsString('debug entry', $content);
        self::assertStringNotContainsString('large object', $content);
        self::assertStringNotContainsString('info entry', $content);
        self::assertStringContainsString('Warning ', $content);
        self::assertStringContainsString('warning entry', $content);
        self::assertStringContainsString('error entry', $content);
    }

    public function testCoreLoggerWritesEverythingOnDebug(): void
    {
        \Configuration::$values[Config::BUCKAROO_LOG_LEVEL] = LogLevel::DEBUG;
        $channel = 'leveltest' . bin2hex(random_bytes(4));
        $file = \CoreLogger::logDirectory() . 'api-' . $channel . '-log-' . date('Y-m-d') . '.txt';
        $this->files[] = $file;

        $logger = new \CoreLogger($channel);
        $logger->logDebug('debug entry');
        $logger->logInfo('info entry');

        $content = (string) file_get_contents($file);
        self::assertStringContainsString('Debug ', $content);
        self::assertStringContainsString('debug entry', $content);
        self::assertStringContainsString('info entry', $content);
    }

    public function testSettingsExposeAndStoreTheLogLevel(): void
    {
        $service = new BuckarooSettingsService();
        self::assertSame(LogLevel::WARNING, $service->getSettings()['log_level']);

        $service->updateSettings($this->settingsData(['log_level' => '0']));
        self::assertSame(LogLevel::DEBUG, \Configuration::get(Config::BUCKAROO_LOG_LEVEL));
        self::assertSame(LogLevel::DEBUG, $service->getSettings()['log_level']);
    }

    public function testSettingsRejectAnInvalidLogLevelButAcceptAMissingOne(): void
    {
        $service = new BuckarooSettingsService();

        self::assertFalse($service->isValidData($this->settingsData(['log_level' => '9'])));
        self::assertTrue($service->isValidData($this->settingsData(['log_level' => '3'])));
        self::assertTrue($service->isValidData($this->settingsData()), 'a page cached before the setting existed can still save');

        $service->updateSettings($this->settingsData());
        self::assertFalse(\Configuration::get(Config::BUCKAROO_LOG_LEVEL), 'a missing value leaves the setting alone');
    }

    public function testUpgradeSetsTheDefaultOnlyWhenNoValidLevelIsStored(): void
    {
        self::assertTrue(upgrade_module_5_5_0(null));
        self::assertSame(LogLevel::WARNING, \Configuration::get(Config::BUCKAROO_LOG_LEVEL));

        \Configuration::$values[Config::BUCKAROO_LOG_LEVEL] = LogLevel::DEBUG;
        self::assertTrue(upgrade_module_5_5_0(null));
        self::assertSame(LogLevel::DEBUG, \Configuration::get(Config::BUCKAROO_LOG_LEVEL));

        \Configuration::$values[Config::BUCKAROO_LOG_LEVEL] = 'garbage';
        self::assertTrue(upgrade_module_5_5_0(null));
        self::assertSame(LogLevel::WARNING, \Configuration::get(Config::BUCKAROO_LOG_LEVEL));
    }

    /**
     * @param array<string,mixed> $overrides
     *
     * @return array<string,mixed>
     */
    private function settingsData(array $overrides = []): array
    {
        return array_merge([
            'website_key' => 'key',
            'secret_key' => 'secret',
            'transaction_description' => '',
            'refundconf' => true,
            'restock' => false,
            'creditSlip' => true,
            'voucher' => false,
            'negativePayment' => false,
            'payment_fee_mode' => 'subtotal',
            'payment_fee_frontend_label' => 'Payment Fee',
            'disable_unpaid_order_email' => false,
        ], $overrides);
    }
}
