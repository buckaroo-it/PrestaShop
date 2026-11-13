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

namespace Buckaroo\PrestaShop\Src\Logs;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Reads the log files that CoreLogger writes to var/logs/buckaroo3/ for the
 * Back Office log page.
 *
 * Daily files are named api-<channel>-log-<Y-m-d>.txt. Every entry starts with
 * "<Level> <Y-m-d H:i:s> <message>" and may continue on the following lines
 * (a "Description:" block with the context data). The legacy report_log.txt
 * ("<date>|||<message>" per line) is listed as well when it exists.
 */
class LogFileReader
{
    public const LEVELS = ['Error', 'Warning', 'Info', 'Debug'];

    /** Only the end of a large file is read, so the page stays fast. */
    public const DEFAULT_MAX_BYTES = 1048576;

    private const DAILY_FILE = '/^api-([a-z0-9_-]+)-log-(\d{4}-\d{2}-\d{2})\.txt$/';
    private const REPORT_FILE = 'report_log.txt';
    private const ENTRY_START = '/^(Error|Warning|Info|Debug) (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) ?(.*)$/';

    /** @var string */
    private $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/\\') . '/';
    }

    /**
     * Log files in the directory, newest day first.
     *
     * @return array<int, array{name: string, channel: string, date: string, size: int}>
     */
    public function listFiles(): array
    {
        $files = [];
        foreach ((array) @scandir($this->directory) as $name) {
            if (!is_string($name) || !is_file($this->directory . $name)) {
                continue;
            }
            $size = (int) @filesize($this->directory . $name);
            if (preg_match(self::DAILY_FILE, $name, $match)) {
                $files[] = ['name' => $name, 'channel' => $match[1], 'date' => $match[2], 'size' => $size];
            } elseif ($name === self::REPORT_FILE) {
                $files[] = ['name' => $name, 'channel' => 'report', 'date' => '', 'size' => $size];
            }
        }

        usort($files, function (array $a, array $b) {
            return [$b['date'], $a['channel']] <=> [$a['date'], $b['channel']];
        });

        return $files;
    }

    /**
     * Entries of one listed file, newest first. A name that is not one of the
     * listed files is never opened.
     *
     * @return array{entries: array<int, array{level: string, time: string, message: string, details: string}>, truncated: bool}
     */
    public function readEntries(string $name, ?string $level = null, int $maxBytes = self::DEFAULT_MAX_BYTES): array
    {
        $result = ['entries' => [], 'truncated' => false];
        if (!in_array($name, array_column($this->listFiles(), 'name'), true)) {
            return $result;
        }

        $content = $this->readTail($this->directory . $name, $maxBytes, $result['truncated']);
        $entries = $name === self::REPORT_FILE ? $this->parseReport($content) : $this->parseDaily($content);

        if ($level !== null && $level !== '') {
            $entries = array_values(array_filter($entries, function (array $entry) use ($level) {
                return $entry['level'] === $level;
            }));
        }
        $result['entries'] = array_reverse($entries);

        return $result;
    }

    private function readTail(string $path, int $maxBytes, bool &$truncated): string
    {
        $size = (int) @filesize($path);
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }
        $truncated = $size > $maxBytes;
        if ($truncated) {
            fseek($handle, -$maxBytes, SEEK_END);
        }
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        if ($truncated) {
            // Drop the partial line the read started in.
            $newline = strpos($content, "\n");
            $content = $newline === false ? '' : substr($content, $newline + 1);
        }

        return $content;
    }

    /**
     * @return array<int, array{level: string, time: string, message: string, details: string}>
     */
    private function parseDaily(string $content): array
    {
        $entries = [];
        $current = null;
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (preg_match(self::ENTRY_START, $line, $match)) {
                if ($current !== null) {
                    $entries[] = $this->finishEntry($current);
                }
                $current = ['level' => $match[1], 'time' => $match[2], 'lines' => [$match[3]]];
            } elseif ($current !== null) {
                // Lines before the first entry header belong to a cut-off entry.
                $current['lines'][] = $line;
            }
        }
        if ($current !== null) {
            $entries[] = $this->finishEntry($current);
        }

        return $entries;
    }

    /**
     * The first non-empty line is the message; the rest (without the
     * "Description:" label) are the details.
     *
     * @param array{level: string, time: string, lines: array<int, string>} $entry
     *
     * @return array{level: string, time: string, message: string, details: string}
     */
    private function finishEntry(array $entry): array
    {
        $lines = $entry['lines'];
        while ($lines && trim($lines[0]) === '') {
            array_shift($lines);
        }
        $message = $lines ? trim((string) array_shift($lines)) : '';
        if ($lines && trim($lines[0]) === 'Description:') {
            array_shift($lines);
        }

        return [
            'level' => $entry['level'],
            'time' => $entry['time'],
            'message' => $message,
            'details' => trim(implode("\n", $lines), "\r\n"),
        ];
    }

    /**
     * @return array<int, array{level: string, time: string, message: string, details: string}>
     */
    private function parseReport(string $content): array
    {
        $entries = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = explode('|||', $line, 2);
            $entries[] = [
                'level' => 'Info',
                'time' => count($parts) === 2 ? trim($parts[0]) : '',
                'message' => trim(count($parts) === 2 ? $parts[1] : $line),
                'details' => '',
            ];
        }

        return $entries;
    }
}
