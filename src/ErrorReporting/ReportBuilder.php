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
 * Turns a Throwable into a single report entry of the collector API (v1).
 * Trace frame arguments are never included.
 */
class ReportBuilder
{
    public const MAX_TRACE_FRAMES = 50;
    public const MAX_PREVIOUS = 5;
    public const MAX_CONTEXT_KEYS = 20;
    public const MAX_STRING_LENGTH = 2000;
    public const MAX_CONTEXT_VALUE_LENGTH = 255;

    public const LEVELS = ['error', 'critical', 'warning'];

    /**
     * @param \Throwable $exception
     * @param string $level
     * @param string $message
     * @param array $context
     *
     * @return array
     */
    public function build(\Throwable $exception, string $level = 'error', string $message = '', array $context = []): array
    {
        $report = [
            'occurred_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            'level' => in_array($level, self::LEVELS, true) ? $level : 'error',
            'exception' => $this->buildException($exception),
        ];

        $message = trim($message);
        if ($message !== '') {
            $report['message'] = $this->limit(Scrubber::scrubString($message), self::MAX_STRING_LENGTH);
        }

        $context = $this->buildContext($context);
        if (!empty($context)) {
            $report['context'] = $context;
        }

        return $report;
    }

    /**
     * Key used to suppress re-sending the same exception across requests.
     * The message is deliberately left out: it often contains order ids.
     */
    public function suppressionFingerprint(\Throwable $exception): string
    {
        return sha1(implode('|', [
            get_class($exception),
            (string) $exception->getCode(),
            $exception->getFile(),
            (string) $exception->getLine(),
        ]));
    }

    /**
     * Key used to drop identical exceptions within one request.
     */
    public function fingerprint(\Throwable $exception, string $level, string $message = ''): string
    {
        return sha1(implode('|', [
            $level,
            get_class($exception),
            (string) $exception->getCode(),
            $exception->getMessage(),
            $exception->getFile(),
            (string) $exception->getLine(),
            $message,
        ]));
    }

    private function buildException(\Throwable $exception): array
    {
        $data = [
            'class' => get_class($exception),
            'message' => $this->limit(Scrubber::scrubString((string) $exception->getMessage()), self::MAX_STRING_LENGTH),
            'code' => (string) $exception->getCode(),
            'file' => Scrubber::relativePath((string) $exception->getFile()),
            'line' => (int) $exception->getLine(),
            'trace' => $this->buildTrace($exception),
        ];

        $previous = $this->buildPrevious($exception);
        if (!empty($previous)) {
            $data['previous'] = $previous;
        }

        return $data;
    }

    private function buildTrace(\Throwable $exception): array
    {
        $frames = [];
        foreach (array_slice($exception->getTrace(), 0, self::MAX_TRACE_FRAMES) as $frame) {
            $item = [];
            if (isset($frame['file'])) {
                $item['file'] = Scrubber::relativePath((string) $frame['file']);
            }
            if (isset($frame['line'])) {
                $item['line'] = (int) $frame['line'];
            }
            if (isset($frame['class'])) {
                $item['class'] = (string) $frame['class'];
            }
            if (isset($frame['function'])) {
                $item['function'] = (string) $frame['function'];
            }
            // Frame 'args' and 'object' are deliberately never copied.
            $frames[] = $item;
        }

        return $frames;
    }

    private function buildPrevious(\Throwable $exception): array
    {
        $previous = [];
        $current = $exception->getPrevious();
        while ($current instanceof \Throwable && count($previous) < self::MAX_PREVIOUS) {
            $previous[] = [
                'class' => get_class($current),
                'message' => $this->limit(Scrubber::scrubString((string) $current->getMessage()), self::MAX_STRING_LENGTH),
                'code' => (string) $current->getCode(),
                'file' => Scrubber::relativePath((string) $current->getFile()),
                'line' => (int) $current->getLine(),
            ];
            $current = $current->getPrevious();
        }

        return $previous;
    }

    /**
     * Flat string map, max 20 keys, scrubbed.
     */
    private function buildContext(array $context): array
    {
        $result = [];
        foreach ($context as $key => $value) {
            if (count($result) >= self::MAX_CONTEXT_KEYS) {
                break;
            }
            if (!is_string($key) || $key === '' || !(is_scalar($value) || $value === null)) {
                continue;
            }
            if (Scrubber::isSensitiveKey($key)) {
                $result[$key] = Scrubber::MASK;
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $result[$key] = $this->limit(Scrubber::scrubString((string) $value), self::MAX_CONTEXT_VALUE_LENGTH);
        }

        return $result;
    }

    private function limit(string $value, int $length): string
    {
        if (strlen($value) <= $length) {
            return $value;
        }

        return substr($value, 0, $length);
    }
}
