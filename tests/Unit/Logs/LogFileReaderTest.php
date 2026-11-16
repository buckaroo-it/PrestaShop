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

use Buckaroo\PrestaShop\Src\Logs\LogFileReader;
use PHPUnit\Framework\TestCase;

final class LogFileReaderTest extends TestCase
{
    /** @var string */
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/buckaroo3-log-reader-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) scandir($this->dir) as $name) {
            if (is_string($name) && is_file($this->dir . $name)) {
                unlink($this->dir . $name);
            }
        }
        rmdir($this->dir);
    }

    public function testListsDailyFilesNewestFirstAndIgnoresOtherFiles(): void
    {
        file_put_contents($this->dir . 'api-request-log-2026-11-11.txt', 'x');
        file_put_contents($this->dir . 'api-response-log-2026-11-12.txt', 'xy');
        file_put_contents($this->dir . 'api-request-log-2026-11-12.txt', 'xyz');
        file_put_contents($this->dir . 'report_log.txt', '');
        file_put_contents($this->dir . 'notes.txt', 'ignored');
        file_put_contents($this->dir . 'index.php', 'ignored');

        $files = (new LogFileReader($this->dir))->listFiles();

        self::assertSame(
            ['api-request-log-2026-11-12.txt', 'api-response-log-2026-11-12.txt', 'api-request-log-2026-11-11.txt', 'report_log.txt'],
            array_column($files, 'name')
        );
        self::assertSame(['request', '2026-11-12', 3], [$files[0]['channel'], $files[0]['date'], $files[0]['size']]);
        self::assertSame('report', $files[3]['channel']);
    }

    public function testMissingDirectoryHasNoFiles(): void
    {
        self::assertSame([], (new LogFileReader($this->dir . 'missing'))->listFiles());
    }

    public function testParsesEntriesWithDescriptionsNewestFirst(): void
    {
        file_put_contents(
            $this->dir . 'api-response-log-2026-11-12.txt',
            "Info 2026-11-12 14:01:01 \n\n\n\n***************** Response start ***********************\n"
            . "Info 2026-11-12 14:01:01 Response validation status\nDescription:\nArray\n(\n    [validated] => 1\n)\n\n\n"
            . "Error 2026-11-12 14:01:02 Push rejected\n"
        );

        $entries = (new LogFileReader($this->dir))->readEntries('api-response-log-2026-11-12.txt')['entries'];

        self::assertCount(3, $entries);
        self::assertSame(['Error', '2026-11-12 14:01:02', 'Push rejected', ''], array_values($entries[0]));
        self::assertSame('Response validation status', $entries[1]['message']);
        self::assertSame("Array\n(\n    [validated] => 1\n)", $entries[1]['details']);
        self::assertSame('***************** Response start ***********************', $entries[2]['message']);
    }

    public function testFiltersByLevel(): void
    {
        file_put_contents(
            $this->dir . 'api-request-log-2026-11-12.txt',
            "Info 2026-11-12 10:00:00 Checkout info\nError 2026-11-12 10:00:01 Validation Error\nWarning 2026-11-12 10:00:02 Slow\n"
        );

        $entries = (new LogFileReader($this->dir))->readEntries('api-request-log-2026-11-12.txt', 'Error')['entries'];

        self::assertSame(['Validation Error'], array_column($entries, 'message'));
    }

    public function testHandlesWindowsLineEndings(): void
    {
        file_put_contents($this->dir . 'api-request-log-2026-11-12.txt', "Error 2026-11-12 10:00:01 Failed\r\nDescription:\r\nreason\r\n");

        $entries = (new LogFileReader($this->dir))->readEntries('api-request-log-2026-11-12.txt')['entries'];

        self::assertSame([['level' => 'Error', 'time' => '2026-11-12 10:00:01', 'message' => 'Failed', 'details' => 'reason']], $entries);
    }

    public function testReadsOnlyTheEndOfALargeFile(): void
    {
        $content = '';
        for ($i = 0; $i < 100; ++$i) {
            $content .= sprintf("Info 2026-11-12 10:00:%02d Entry %d\nDescription:\nline\n", $i % 60, $i);
        }
        file_put_contents($this->dir . 'api-request-log-2026-11-12.txt', $content);

        $result = (new LogFileReader($this->dir))->readEntries('api-request-log-2026-11-12.txt', null, 200);

        self::assertTrue($result['truncated']);
        self::assertSame('Entry 99', $result['entries'][0]['message']);
        self::assertLessThan(100, count($result['entries']));
        foreach ($result['entries'] as $entry) {
            self::assertMatchesRegularExpression('/^Entry \d+$/', $entry['message']);
        }
    }

    public function testParsesTheLegacyReportLog(): void
    {
        file_put_contents($this->dir . 'report_log.txt', "2026-11-12 10:00:00|||First\n2026-11-12 10:05:00|||Second\n");

        $entries = (new LogFileReader($this->dir))->readEntries('report_log.txt')['entries'];

        self::assertSame(['Second', 'First'], array_column($entries, 'message'));
        self::assertSame('2026-11-12 10:05:00', $entries[0]['time']);
    }

    /**
     * @dataProvider unlistedNames
     */
    public function testNeverOpensAFileThatIsNotListed(string $name): void
    {
        file_put_contents($this->dir . 'notes.txt', "Error 2026-11-12 10:00:00 Secret\n");

        self::assertSame(['entries' => [], 'truncated' => false], (new LogFileReader($this->dir))->readEntries($name));
    }

    public static function unlistedNames(): array
    {
        return [
            'other file in the directory' => ['notes.txt'],
            'path traversal' => ['../../config/settings.inc.php'],
            'traversal with a valid suffix' => ['../api-request-log-2026-11-12.txt'],
            'empty' => [''],
        ];
    }
}
