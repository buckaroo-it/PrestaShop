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
 * Sends plugin exceptions to the Buckaroo plugin log collector, only when the
 * merchant opted in. Reports are buffered per request (deduplicated, max 20)
 * and sent once at the very end of the request. Nothing in here may ever
 * break the shop: every failure is swallowed.
 */
class ErrorReporter
{
    /**
     * Collector endpoint (v1).
     */
    public const ENDPOINT = 'https://plugin-logs.buckaroo.io/api/v1/reports';

    /**
     * Public, non-secret ingest key (comparable to a Sentry DSN).
     */
    public const BUCKAROO_PLUGIN_LOG_INGEST_KEY = 'public-plugin-ingest-key';

    public const PLATFORM = 'prestashop';
    public const MAX_REPORTS_PER_REQUEST = 20;

    /**
     * The same exception (class, code, file, line) is sent at most once per window.
     */
    public const SUPPRESSION_WINDOW_SECONDS = 900;

    /** @var self|null */
    private static $instance;

    /** @var callable(): bool */
    private $consentChecker;

    /** @var ShopMetadataProvider */
    private $metadataProvider;

    /** @var TransportInterface */
    private $transport;

    /** @var ReportBuilder */
    private $reportBuilder;

    /** @var string */
    private $endpoint;

    /** @var bool */
    private $flushOnShutdown;

    /** @var bool */
    private $shutdownRegistered = false;

    /** @var array<int,array> */
    private $reports = [];

    /** @var array<string,bool> */
    private $fingerprints = [];

    /** @var string|null */
    private $paymentMethod;

    /** @var SuppressionStoreInterface */
    private $suppressionStore;

    /** @var callable(): int */
    private $clock;

    /**
     * Suppression fingerprints of the buffered reports, same order as $reports.
     *
     * @var array<int,string>
     */
    private $suppressionFingerprints = [];

    /**
     * @param callable|null $consentChecker returns true only when the merchant opted in
     * @param ShopMetadataProvider|null $metadataProvider
     * @param TransportInterface|null $transport
     * @param string|null $endpoint defaults to {@see resolveEndpoint()}
     * @param bool $flushOnShutdown
     * @param SuppressionStoreInterface|null $suppressionStore cross-request suppression, file based by default
     * @param callable|null $clock returns the current unix timestamp
     */
    public function __construct(
        ?callable $consentChecker = null,
        ?ShopMetadataProvider $metadataProvider = null,
        ?TransportInterface $transport = null,
        ?string $endpoint = null,
        bool $flushOnShutdown = true,
        ?SuppressionStoreInterface $suppressionStore = null,
        ?callable $clock = null
    ) {
        $this->suppressionStore = $suppressionStore ?: new FileSuppressionStore();
        $this->clock = $clock ?: 'time';
        $this->consentChecker = $consentChecker ?: [ErrorReportingConsent::class, 'isGranted'];
        $this->metadataProvider = $metadataProvider ?: new ShopMetadataProvider();
        $this->transport = $transport ?: new CurlTransport();
        $this->reportBuilder = new ReportBuilder();
        $this->endpoint = $endpoint ?? self::resolveEndpoint();
        $this->flushOnShutdown = $flushOnShutdown;
    }

    /**
     * The collector URL. Development and staging shops can point it elsewhere
     * with the BUCKAROO_PLUGIN_LOG_ENDPOINT constant or environment variable.
     */
    public static function resolveEndpoint(): string
    {
        if (defined('BUCKAROO_PLUGIN_LOG_ENDPOINT') && is_string(constant('BUCKAROO_PLUGIN_LOG_ENDPOINT'))) {
            return (string) constant('BUCKAROO_PLUGIN_LOG_ENDPOINT');
        }

        $fromEnv = getenv('BUCKAROO_PLUGIN_LOG_ENDPOINT');

        return is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : self::ENDPOINT;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Replace the shared instance (tests); null resets it.
     */
    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Safe static entry point used by the plugin logger and catch blocks.
     *
     * @param \Throwable $exception
     * @param string $level error|critical|warning
     * @param string $message log message logged alongside the exception
     * @param array $context flat map, e.g. payment_method, action, order_id
     */
    public static function captureException(\Throwable $exception, string $level = 'error', string $message = '', array $context = []): void
    {
        try {
            self::getInstance()->report($exception, $level, $message, $context);
        } catch (\Throwable $e) {
            // Error reporting must never break the shop.
        }
    }

    public function report(\Throwable $exception, string $level = 'error', string $message = '', array $context = []): void
    {
        try {
            if (!$this->hasConsent()) {
                return;
            }

            if (count($this->reports) >= self::MAX_REPORTS_PER_REQUEST) {
                return;
            }

            $fingerprint = $this->reportBuilder->fingerprint($exception, $level, $message);
            if (isset($this->fingerprints[$fingerprint])) {
                return;
            }
            $this->fingerprints[$fingerprint] = true;

            // Skip exceptions already sent by an earlier request within the window.
            $suppressionFingerprint = $this->reportBuilder->suppressionFingerprint($exception);
            if ($this->isRecentlySent($suppressionFingerprint)) {
                return;
            }

            if ($this->paymentMethod === null && isset($context['payment_method']) && is_scalar($context['payment_method'])) {
                $this->paymentMethod = (string) $context['payment_method'];
            }

            $this->reports[] = $this->reportBuilder->build($exception, $level, $message, $context);
            $this->suppressionFingerprints[] = $suppressionFingerprint;
            $this->registerShutdown();
        } catch (\Throwable $e) {
            // Swallow.
        }
    }

    /**
     * Send all buffered reports in one request and clear the buffer.
     */
    public function flush(): void
    {
        try {
            if (empty($this->reports)) {
                return;
            }

            $reports = $this->reports;
            $suppressionFingerprints = $this->suppressionFingerprints;
            $this->reports = [];
            $this->suppressionFingerprints = [];

            // Consent is re-checked right before anything leaves the shop.
            if (!$this->hasConsent()) {
                return;
            }

            $body = json_encode(
                $this->buildPayload($reports),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
            if (!is_string($body)) {
                return;
            }

            // Recorded before the HTTP call: the response is never waited for.
            $this->recordSent($suppressionFingerprints);

            $this->transport->send($this->endpoint, $this->buildHeaders(), $body);
        } catch (\Throwable $e) {
            // Swallow: network failures, timeouts, anything.
        }
    }

    /**
     * @param array $reports
     *
     * @return array
     */
    public function buildPayload(array $reports): array
    {
        $metadata = $this->metadataProvider->getMetadata($this->paymentMethod);

        $payload = [
            'platform' => self::PLATFORM,
            'plugin_version' => (string) ($metadata['plugin_version'] ?? 'unknown'),
            'platform_version' => (string) ($metadata['platform_version'] ?? 'unknown'),
            'php_version' => (string) ($metadata['php_version'] ?? PHP_VERSION),
            'environment' => ($metadata['environment'] ?? 'test') === 'live' ? 'live' : 'test',
            'shop_url' => (string) ($metadata['shop_url'] ?? ''),
        ];

        if (!empty($metadata['website_key'])) {
            $payload['website_key'] = (string) $metadata['website_key'];
        }

        $payload['reports'] = array_values(array_slice($reports, 0, self::MAX_REPORTS_PER_REQUEST));

        return $payload;
    }

    /**
     * @return array<int,array>
     */
    public function getBufferedReports(): array
    {
        return $this->reports;
    }

    /**
     * Shutdown callback: runs after the other shutdown functions, finishes the
     * response for FPM clients first so the HTTP call does not delay the shopper.
     */
    public function flushAtShutdown(): void
    {
        try {
            if (empty($this->reports)) {
                return;
            }
            if (PHP_SAPI === 'fpm-fcgi' && function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        } catch (\Throwable $e) {
            // Ignore, still try to flush.
        }

        $this->flush();
    }

    private function now(): int
    {
        return (int) call_user_func($this->clock);
    }

    private function isRecentlySent(string $fingerprint): bool
    {
        try {
            return $this->suppressionStore->wasSentSince($fingerprint, $this->now() - self::SUPPRESSION_WINDOW_SECONDS);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param string[] $fingerprints
     */
    private function recordSent(array $fingerprints): void
    {
        try {
            $now = $this->now();
            $this->suppressionStore->recordSent(
                array_values(array_unique($fingerprints)),
                $now,
                $now - self::SUPPRESSION_WINDOW_SECONDS
            );
        } catch (\Throwable $e) {
            // A failing store only means the next request may send it again.
        }
    }

    private function hasConsent(): bool
    {
        try {
            return call_user_func($this->consentChecker) === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string,string>
     */
    private function buildHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Buckaroo-Ingest-Key' => self::BUCKAROO_PLUGIN_LOG_INGEST_KEY,
            'User-Agent' => 'buckaroo-' . self::PLATFORM . '/' . $this->metadataProvider->getPluginVersion(),
        ];
    }

    private function registerShutdown(): void
    {
        if (!$this->flushOnShutdown || $this->shutdownRegistered) {
            return;
        }
        $this->shutdownRegistered = true;

        // Registering from inside a shutdown function puts the flush at the
        // end of the shutdown queue, after PrestaShop's own handlers.
        register_shutdown_function(function () {
            register_shutdown_function([$this, 'flushAtShutdown']);
        });
    }
}
