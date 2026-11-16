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

namespace Buckaroo\PrestaShop\Src\Express;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Express button settings of a wallet payment method, stored in its method
 * configuration: the pages the button is shown on, whether the method stays
 * listed in the checkout, the wallet credentials and the button look.
 */
abstract class AbstractExpressSettings
{
    public const PLACEMENT_PRODUCT = 'product';
    public const PLACEMENT_CART = 'cart';
    public const PLACEMENT_CHECKOUT = 'checkout';

    /** @var string[] */
    public const PLACEMENTS = [self::PLACEMENT_PRODUCT, self::PLACEMENT_CART, self::PLACEMENT_CHECKOUT];

    /**
     * Hooks the express buttons need: assets (header), product page, cart page, checkout page.
     *
     * @var string[]
     */
    public const HOOKS = ['displayHeader', 'displayProductAdditionalInfo', 'displayExpressCheckout', 'displayPaymentTop'];

    public const KEY_MODE = 'mode';
    public const KEY_SHOW_ON = 'express_show_on';
    public const KEY_CHECKOUT_METHOD = 'checkout_method';
    public const KEY_BUTTON_STYLE = 'button_style';
    public const KEY_BUTTON_TYPE = 'button_type';

    /** @var array<string, mixed> */
    protected $config;

    /**
     * @param array<string, mixed>|null $config the stored method configuration
     */
    public function __construct(?array $config)
    {
        $this->config = $config ?? [];
    }

    abstract public function getWallet(): Wallet;

    /**
     * Whether the wallet credentials the express button needs are filled in.
     */
    abstract public function isConfigured(): bool;

    /**
     * @return string[] button styles, the default first
     */
    abstract public static function buttonStyles(): array;

    /**
     * @return string[] button types, the default first
     */
    abstract public static function buttonTypes(): array;

    /**
     * Normalise the credential keys of a posted configuration.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    abstract protected static function sanitizeCredentials(array $data): array;

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
     * Whether the wallet stays listed as a checkout payment method next to the
     * express button.
     */
    public function isListedInCheckout(): bool
    {
        return self::toBool($this->config[self::KEY_CHECKOUT_METHOD] ?? true);
    }

    public function isLiveMode(): bool
    {
        return ($this->config[self::KEY_MODE] ?? '') === 'live';
    }

    public function getButtonStyle(): string
    {
        $styles = static::buttonStyles();
        $style = $this->config[self::KEY_BUTTON_STYLE] ?? '';

        return in_array($style, $styles, true) ? $style : $styles[0];
    }

    public function getButtonType(): string
    {
        $types = static::buttonTypes();
        $type = $this->config[self::KEY_BUTTON_TYPE] ?? '';

        return in_array($type, $types, true) ? $type : $types[0];
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

        $styles = static::buttonStyles();
        if (array_key_exists(self::KEY_BUTTON_STYLE, $data) && !in_array($data[self::KEY_BUTTON_STYLE], $styles, true)) {
            $data[self::KEY_BUTTON_STYLE] = $styles[0];
        }

        $types = static::buttonTypes();
        if (array_key_exists(self::KEY_BUTTON_TYPE, $data) && !in_array($data[self::KEY_BUTTON_TYPE], $types, true)) {
            $data[self::KEY_BUTTON_TYPE] = $types[0];
        }

        return static::sanitizeCredentials($data);
    }

    /**
     * A credential as stored, trimmed; empty when it is not a string.
     *
     * @param mixed $value
     */
    protected static function cleanCredential($value, string $pattern): string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match($pattern, $value) ? $value : '';
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
