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
 * Client-side scrubbing of error reports (defense in depth; the collector
 * scrubs again). Masks card numbers, e-mail addresses, IBANs and values of
 * secret-looking keys.
 */
class Scrubber
{
    public const MASK = '[REDACTED]';
    public const MASK_CARD = '[REDACTED_CARD]';
    public const MASK_EMAIL = '[REDACTED_EMAIL]';
    public const MASK_IBAN = '[REDACTED_IBAN]';

    /**
     * Key names whose values are always masked.
     */
    private const SENSITIVE_KEY_PATTERN = '/(pass(word|wd)?|secret|token|api[_-]?key|apikey|auth(orization)?|private[_-]?key|cvc|cvv)/i';

    /**
     * key=value / key: value / "key":"value" pairs inside free text.
     */
    private const SENSITIVE_PAIR_PATTERN = '/((?:pass(?:word|wd)?|secret(?:[_-]?key)?|token|api[_-]?key|apikey|authorization|private[_-]?key)["\']?\s*[:=]\s*["\']?)([^"\'\s&,;]+)/i';

    private const EMAIL_PATTERN = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';

    /**
     * IBAN: country code, 2 check digits, 11-30 alphanumerics (optionally grouped by spaces).
     */
    private const IBAN_PATTERN = '/\b[A-Z]{2}[0-9]{2}(?:[ ]?[A-Z0-9]){11,30}\b/';

    /**
     * 13-19 digits, optionally separated by single spaces or dashes.
     */
    private const CARD_PATTERN = '/(?<![0-9])[0-9](?:[ \-]?[0-9]){12,18}(?![0-9])/';

    public static function scrubString(string $value): string
    {
        $value = (string) preg_replace(self::SENSITIVE_PAIR_PATTERN, '$1' . self::MASK, $value);
        $value = (string) preg_replace(self::EMAIL_PATTERN, self::MASK_EMAIL, $value);
        $value = (string) preg_replace(self::IBAN_PATTERN, self::MASK_IBAN, $value);
        $value = (string) preg_replace_callback(
            self::CARD_PATTERN,
            static function (array $match): string {
                return self::passesLuhn($match[0]) ? self::MASK_CARD : $match[0];
            },
            $value
        );

        return $value;
    }

    public static function isSensitiveKey($key): bool
    {
        return is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1;
    }

    /**
     * Recursively scrub an array: sensitive keys are masked, strings are scrubbed.
     *
     * @param array $data
     *
     * @return array
     */
    public static function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (self::isSensitiveKey($key)) {
                $data[$key] = self::MASK;
            } elseif (is_array($value)) {
                $data[$key] = self::scrubArray($value);
            } elseif (is_string($value)) {
                $data[$key] = self::scrubString($value);
            }
        }

        return $data;
    }

    /**
     * Strip the shop root from file paths so no server layout is leaked.
     */
    public static function relativePath(string $file): string
    {
        if (defined('_PS_ROOT_DIR_')) {
            $root = rtrim((string) _PS_ROOT_DIR_, '/\\') . DIRECTORY_SEPARATOR;
            if ($root !== DIRECTORY_SEPARATOR && strpos($file, $root) === 0) {
                return substr($file, strlen($root));
            }
        }

        return $file;
    }

    /**
     * Check a digit string with the Luhn checksum that card numbers use,
     * so order ids and timestamps are not masked as cards
     *
     * @param string $number
     * @return bool
     */
    private static function passesLuhn(string $number): bool
    {
        $digits = (string) preg_replace('/\D/', '', $number);
        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];
            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $double = !$double;
        }

        return $sum % 10 === 0;
    }
}
