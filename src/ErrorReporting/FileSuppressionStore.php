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

namespace Buckaroo\PrestaShop\Src\ErrorReporting;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * JSON file mapping fingerprint => unix timestamp of the last send.
 * Kept out of the Configuration table so errors never cause DB writes.
 */
class FileSuppressionStore implements SuppressionStoreInterface
{
    public const MAX_ENTRIES = 500;

    /** @var string|null */
    private $path;

    /** @var array<string,int>|null */
    private $entries;

    /**
     * @param string|null $path defaults to _PS_CACHE_DIR_ . 'buckaroo3/error_reporting.json'
     */
    public function __construct(?string $path = null)
    {
        if ($path === null && defined('_PS_CACHE_DIR_')) {
            $path = rtrim((string) _PS_CACHE_DIR_, '/\\') . '/buckaroo3/error_reporting.json';
        }
        $this->path = $path;
    }

    public function wasSentSince(string $fingerprint, int $notBefore): bool
    {
        try {
            $entries = $this->load();

            return isset($entries[$fingerprint]) && $entries[$fingerprint] >= $notBefore;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function recordSent(array $fingerprints, int $now, int $notBefore): void
    {
        try {
            if ($this->path === null || empty($fingerprints)) {
                return;
            }

            // Re-read so entries written by concurrent requests are kept.
            $this->entries = null;
            $entries = $this->load();
            foreach ($fingerprints as $fingerprint) {
                $entries[(string) $fingerprint] = $now;
            }

            $entries = array_filter($entries, function ($timestamp) use ($notBefore) {
                return $timestamp >= $notBefore;
            });
            if (count($entries) > self::MAX_ENTRIES) {
                arsort($entries);
                $entries = array_slice($entries, 0, self::MAX_ENTRIES, true);
            }

            $this->entries = $entries;

            $dir = dirname($this->path);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return;
            }
            $json = json_encode($entries);
            if (!is_string($json)) {
                return;
            }
            @file_put_contents($this->path, $json, LOCK_EX);
        } catch (\Throwable $e) {
            // Never break the shop.
        }
    }

    /**
     * @return array<string,int>
     */
    private function load(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $entries = [];
        if ($this->path !== null && is_file($this->path) && is_readable($this->path)) {
            $raw = @file_get_contents($this->path);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                foreach ($decoded as $fingerprint => $timestamp) {
                    if (is_string($fingerprint) && is_int($timestamp)) {
                        $entries[$fingerprint] = $timestamp;
                    }
                }
            }
        }

        return $this->entries = $entries;
    }
}
