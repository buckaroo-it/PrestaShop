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

namespace Buckaroo\PrestaShop\Tests\Unit\Install;

use Buckaroo\PrestaShop\Src\Install\LogDirectoryMigrator;
use PHPUnit\Framework\TestCase;

final class LogDirectoryMigratorTest extends TestCase
{
    /** @var string */
    private $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/buckaroo3-log-migration-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/api/log', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->base);
    }

    public function testMovesLogFilesAndRemovesTheOldDirectory(): void
    {
        file_put_contents($this->base . '/api/log/api-response-log-2026-10-21.txt', "old response\n");
        file_put_contents($this->base . '/api/log/report_log.txt', "2026-10-21 10:00:00|||Order 1\n");
        file_put_contents($this->base . '/api/log/index.php', '<?php');
        file_put_contents($this->base . '/api/log/.htaccess', 'deny from all');

        $moved = $this->migrator()->migrate();

        self::assertSame(2, $moved);
        self::assertDirectoryDoesNotExist($this->base . '/api/log');
        self::assertSame("old response\n", file_get_contents($this->base . '/var/logs/buckaroo3/api-response-log-2026-10-21.txt'));
        self::assertSame("2026-10-21 10:00:00|||Order 1\n", file_get_contents($this->base . '/var/logs/buckaroo3/report_log.txt'));
        self::assertFileDoesNotExist($this->base . '/var/logs/buckaroo3/index.php');
        self::assertFileDoesNotExist($this->base . '/var/logs/buckaroo3/.htaccess');
    }

    public function testAppendsToALogTheNewLoggerAlreadyStarted(): void
    {
        mkdir($this->base . '/var/logs/buckaroo3', 0777, true);
        file_put_contents($this->base . '/var/logs/buckaroo3/report_log.txt', "new entry\n");
        file_put_contents($this->base . '/api/log/report_log.txt', "old entry\n");

        self::assertSame(1, $this->migrator()->migrate());
        self::assertSame("new entry\nold entry\n", file_get_contents($this->base . '/var/logs/buckaroo3/report_log.txt'));
        self::assertFileDoesNotExist($this->base . '/api/log/report_log.txt');
    }

    public function testDoesNothingWhenTheOldDirectoryIsGone(): void
    {
        rmdir($this->base . '/api/log');

        self::assertSame(0, $this->migrator()->migrate());
        self::assertDirectoryDoesNotExist($this->base . '/var/logs/buckaroo3');
    }

    public function testLeavesTheOldDirectoryWhenItStillHoldsSomethingElse(): void
    {
        mkdir($this->base . '/api/log/archive');
        file_put_contents($this->base . '/api/log/api-request-log-2026-03-12.txt', "request\n");

        self::assertSame(1, $this->migrator()->migrate());
        self::assertDirectoryExists($this->base . '/api/log/archive', 'subdirectories are not touched');
        self::assertFileExists($this->base . '/var/logs/buckaroo3/api-request-log-2026-03-12.txt');
    }

    private function migrator(): LogDirectoryMigrator
    {
        return new LogDirectoryMigrator($this->base . '/api/log/', $this->base . '/var/logs/buckaroo3/');
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach ((array) scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
