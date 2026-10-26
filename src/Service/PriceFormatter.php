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

namespace Buckaroo\PrestaShop\Src\Service;

use Buckaroo\PrestaShop\Src\Context\ContextAwareTrait;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Locale aware price formatting shared by hooks, templates and controllers.
 *
 * PrestaShop 1.7.6+ formats prices through the CLDR locale of the current
 * context (currency symbol, decimal and thousands separators follow the shop
 * locale). Older 1.7 releases only offer Tools::displayPrice(). This class hides
 * that difference so callers always pass numeric values and receive a display
 * string; calculations must keep using the numeric amounts.
 */
final class PriceFormatter
{
    use ContextAwareTrait;

    /**
     * @param object|null $context Context object or provider exposing getContext()
     */
    public function __construct($context = null)
    {
        $this->setContext($context);
    }

    /**
     * @param float|int|string $amount Numeric amount (not a formatted string)
     * @param string|null $currencyIsoCode ISO 4217 code; defaults to the context currency
     */
    public function format($amount, ?string $currencyIsoCode = null): string
    {
        $amount = (float) $amount;
        $context = $this->getContext();

        if ($currencyIsoCode === null || $currencyIsoCode === '') {
            $currencyIsoCode = $this->getContextCurrencyIsoCode($context);
        }

        if ($context !== null && $currencyIsoCode !== '' && method_exists('\Tools', 'getContextLocale')) {
            try {
                $locale = \Tools::getContextLocale($context);
                if (is_object($locale) && method_exists($locale, 'formatPrice')) {
                    return (string) $locale->formatPrice($amount, $currencyIsoCode);
                }
            } catch (\Throwable $exception) {
                // Fall through to the legacy formatter below.
            }
        }

        return $this->formatLegacy($amount, $currencyIsoCode);
    }

    /**
     * Formats an amount in the currency of an order (by currency id).
     *
     * @param float|int|string $amount
     */
    public function formatForCurrencyId($amount, int $currencyId): string
    {
        $isoCode = '';
        if ($currencyId > 0 && class_exists('\Currency') && method_exists('\Currency', 'getIsoCodeById')) {
            $isoCode = (string) \Currency::getIsoCodeById($currencyId);
        }

        return $this->format($amount, $isoCode !== '' ? $isoCode : null);
    }

    /**
     * @param object|null $context
     */
    private function getContextCurrencyIsoCode($context): string
    {
        if ($context !== null && isset($context->currency) && is_object($context->currency) && isset($context->currency->iso_code)) {
            return (string) $context->currency->iso_code;
        }

        return '';
    }

    /**
     * PrestaShop < 1.7.6 and non-PrestaShop runtimes (unit tests).
     */
    private function formatLegacy(float $amount, string $currencyIsoCode): string
    {
        // Tools::displayPrice() was removed in PrestaShop 9, hence the dynamic call.
        $legacyFormatter = 'displayPrice';
        if (class_exists('\Tools') && method_exists('\Tools', $legacyFormatter)) {
            $currency = null;
            if ($currencyIsoCode !== '' && class_exists('\Currency') && method_exists('\Currency', 'getIdByIsoCode')) {
                $currencyId = (int) \Currency::getIdByIsoCode($currencyIsoCode);
                if ($currencyId > 0) {
                    $currency = new \Currency($currencyId);
                }
            }

            try {
                // Tools::displayPrice() on 1.7.6+ delegates to the CLDR locale and throws when
                // no kernel container exists (CLI/standalone scripts): fall back to the neutral format.
                return (string) call_user_func(['\Tools', $legacyFormatter], $amount, $currency);
            } catch (\Throwable $exception) {
                // Fall through to the neutral representation below.
            }
        }

        $formatted = number_format($amount, 2, '.', '');

        return $currencyIsoCode !== '' ? $formatted . ' ' . $currencyIsoCode : $formatted;
    }
}
