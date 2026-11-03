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

namespace Buckaroo\PrestaShop\Src\ApplePay;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Apple Pay express settings, read from the Apple Pay method configuration
 * (the same keys as the WooCommerce plugin).
 */
final class ExpressSettings
{
    public const METHOD_NAME = 'applepay';

    public const PLACEMENT_PRODUCT = 'product';
    public const PLACEMENT_CART = 'cart';
    public const PLACEMENT_CHECKOUT = 'checkout';

    /** @var string[] */
    public const PLACEMENTS = [self::PLACEMENT_PRODUCT, self::PLACEMENT_CART, self::PLACEMENT_CHECKOUT];

    /** @var string[] */
    public const BUTTON_STYLES = ['black', 'white', 'white-outline'];

    /** @var string[] */
    public const BUTTON_TYPES = ['plain', 'buy', 'check-out', 'order', 'book', 'subscribe'];

    public const KEY_SHOW_ON = 'express_show_on';
    public const KEY_CHECKOUT_METHOD = 'checkout_method';
    public const KEY_MERCHANT_GUID = 'merchant_guid';
    public const KEY_BUTTON_STYLE = 'button_style';
    public const KEY_BUTTON_TYPE = 'button_type';

    /** @var array<string, mixed> */
    private $config;

    /**
     * @param array<string, mixed>|null $config the stored Apple Pay method configuration
     */
    public function __construct(?array $config)
    {
        $this->config = $config ?? [];
    }

    /**
     * Pages the express button is shown on. A configuration saved before this
     * setting existed shows the button everywhere; an emptied selection hides it.
     *
     * @return string[]
     */
    public function getPlacements(): array
    {
        if (!array_key_exists(self::KEY_SHOW_ON, $this->config)) {
            return self::PLACEMENTS;
        }

        return self::onlyKnownPlacements($this->config[self::KEY_SHOW_ON]);
    }

    public function showsOn(string $placement): bool
    {
        return in_array($placement, $this->getPlacements(), true);
    }

    /**
     * Whether Apple Pay stays listed as a checkout payment method next to the
     * express button.
     */
    public function isListedInCheckout(): bool
    {
        return self::toBool($this->config[self::KEY_CHECKOUT_METHOD] ?? true);
    }

    public function getMerchantGuid(): string
    {
        $guid = $this->config[self::KEY_MERCHANT_GUID] ?? '';

        return is_string($guid) ? trim($guid) : '';
    }

    /**
     * The native Apple Pay session is validated by Buckaroo for this GUID.
     */
    public function isConfigured(): bool
    {
        return $this->getMerchantGuid() !== '';
    }

    public function getButtonStyle(): string
    {
        $style = $this->config[self::KEY_BUTTON_STYLE] ?? '';

        return in_array($style, self::BUTTON_STYLES, true) ? $style : self::BUTTON_STYLES[0];
    }

    public function getButtonType(): string
    {
        $type = $this->config[self::KEY_BUTTON_TYPE] ?? '';

        return in_array($type, self::BUTTON_TYPES, true) ? $type : self::BUTTON_TYPES[0];
    }

    /**
     * Normalise the express keys of a configuration posted from the admin, so
     * only known values are stored. Other keys are returned unchanged.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function sanitize(array $data): array
    {
        if (array_key_exists(self::KEY_SHOW_ON, $data)) {
            $data[self::KEY_SHOW_ON] = self::onlyKnownPlacements($data[self::KEY_SHOW_ON]);
        }

        if (array_key_exists(self::KEY_CHECKOUT_METHOD, $data)) {
            $data[self::KEY_CHECKOUT_METHOD] = self::toBool($data[self::KEY_CHECKOUT_METHOD]) ? '1' : '0';
        }

        if (array_key_exists(self::KEY_MERCHANT_GUID, $data)) {
            $guid = is_string($data[self::KEY_MERCHANT_GUID]) ? trim($data[self::KEY_MERCHANT_GUID]) : '';
            $data[self::KEY_MERCHANT_GUID] = preg_match('/^[A-Za-z0-9-]{0,64}$/', $guid) ? $guid : '';
        }

        if (array_key_exists(self::KEY_BUTTON_STYLE, $data) && !in_array($data[self::KEY_BUTTON_STYLE], self::BUTTON_STYLES, true)) {
            $data[self::KEY_BUTTON_STYLE] = self::BUTTON_STYLES[0];
        }

        if (array_key_exists(self::KEY_BUTTON_TYPE, $data) && !in_array($data[self::KEY_BUTTON_TYPE], self::BUTTON_TYPES, true)) {
            $data[self::KEY_BUTTON_TYPE] = self::BUTTON_TYPES[0];
        }

        return $data;
    }

    /**
     * @param mixed $placements
     *
     * @return string[]
     */
    private static function onlyKnownPlacements($placements): array
    {
        if (!is_array($placements)) {
            return [];
        }

        return array_values(array_intersect(self::PLACEMENTS, $placements));
    }

    /**
     * @param mixed $value
     */
    private static function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return !in_array(strtolower(trim((string) $value)), ['0', 'false', 'no', 'off', ''], true);
    }
}
