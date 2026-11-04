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
 * Minimal fire-and-forget HTTP transport (connect timeout 2s, total 3s, no retries).
 *
 * Plain ext-curl is used on purpose instead of Guzzle: depending on the
 * PrestaShop version the core may already have loaded an incompatible Guzzle
 * major version before the module's own vendor directory is autoloaded.
 */
class CurlTransport implements TransportInterface
{
    public const CONNECT_TIMEOUT_MS = 2000;
    public const TIMEOUT_MS = 3000;

    public function send(string $url, array $headers, string $body): void
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        if (function_exists('curl_init')) {
            $this->sendWithCurl($url, $headerLines, $body);

            return;
        }

        $this->sendWithStream($url, $headerLines, $body);
    }

    private function sendWithCurl(string $url, array $headerLines, string $body): void
    {
        $handle = curl_init($url);
        if ($handle === false) {
            return;
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_CONNECTTIMEOUT_MS => self::CONNECT_TIMEOUT_MS,
            CURLOPT_TIMEOUT_MS => self::TIMEOUT_MS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        curl_exec($handle);
        // PHP 8.0+ frees the handle automatically and deprecates curl_close() in 8.5.
        if (PHP_VERSION_ID < 80000) {
            curl_close($handle);
        }
    }

    private function sendWithStream(string $url, array $headerLines, string $body): void
    {
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines),
                'content' => $body,
                'timeout' => self::TIMEOUT_MS / 1000,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ]);

        @file_get_contents($url, false, $context);
    }
}
