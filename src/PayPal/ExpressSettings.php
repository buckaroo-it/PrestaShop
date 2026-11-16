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

namespace Buckaroo\PrestaShop\Src\PayPal;

use Buckaroo\PrestaShop\Src\Express\AbstractExpressSettings;
use Buckaroo\PrestaShop\Src\Express\Wallet;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * PayPal express settings, read from the PayPal method configuration, and the
 * PayPal client the button is rendered with.
 *
 * The PayPal order behind the button is created by Buckaroo
 * (/api/paypal/createOrder) for Buckaroo's PayPal platform client, as the
 * Buckaroo Client SDK does (BuckarooSdk.PayPal). The client IDs below are the
 * ones of that SDK, for the live (checkout.buckaroo.nl) and the test
 * (testcheckout.buckaroo.nl) environment.
 */
final class ExpressSettings extends AbstractExpressSettings
{
    public const METHOD_NAME = Wallet::PAYPAL;

    /**
     * PayPal button colors, gold (PayPal's standard) first.
     *
     * @var string[]
     */
    public const BUTTON_STYLES = ['gold', 'blue', 'silver', 'white', 'black'];

    /**
     * PayPal button labels: "paypal" (the plain PayPal logo, the default),
     * "Checkout", "Buy now" and "Pay with".
     *
     * @var string[]
     */
    public const BUTTON_TYPES = ['paypal', 'checkout', 'buynow', 'pay'];

    /** PayPal merchant ID (PayPal business information); required for live payments. */
    public const KEY_MERCHANT_ID = 'express_merchant_id';
    /** PayPal sandbox merchant ID; optional, used in test mode. */
    public const KEY_SANDBOX_MERCHANT_ID = 'express_sandbox_merchant_id';
    /** Rounded (pill) instead of rectangular button. */
    public const KEY_BUTTON_ROUNDED = 'button_rounded';
    /** Send the customer name and address to PayPal for seller protection. */
    public const KEY_SELLER_PROTECTION = 'seller_protection';

    public const CHECKOUT_URL_LIVE = 'https://checkout.buckaroo.nl';
    public const CHECKOUT_URL_TEST = 'https://testcheckout.buckaroo.nl';

    private const CLIENT_ID_LIVE = 'ATv1oKfBmc76Zzl8rAMai_OwpXIp9CsDTMzEceayY7X2Sy8t6bQT2rm7DIC7LYbfkch9m9S3R3amkeyU';
    private const CLIENT_ID_TEST = 'Ab8jz-WnSLFLHjn-i_Bs0k_eWmRThzLHKpWnFwtwuPlQigUZsZWzgDF1AXQG3dHKPgW2YWKkGtJIjFty';
    /** Clients for merchants that collect through Buckaroo; their merchant ID starts with "c_". */
    private const COLLECTING_CLIENT_ID_LIVE = 'ARo6emSQPfMjeY3Jc1ceOfn-2kgNJFVR2CNuKJxOWzq5HtuuSyzzvRvv2bYJFB2hRozmUaVbWLqCTSFO';
    private const COLLECTING_CLIENT_ID_TEST = 'ATSqlzwQKmBf-mKZd0AJTsjSUNesNhNELIe_JzBvcz4JcOm5btb9MhTBAAF1x6-Z6pkfY4gjHJNnHJ33';
    private const COLLECTING_PREFIX = 'c_';

    private const PARTNER_ATTRIBUTION_ID = 'NL_BUCKAROO_PPCP';
    private const COLLECTING_PARTNER_ATTRIBUTION_ID = 'Buckaroo_PSP_MP';

    public function getWallet(): Wallet
    {
        return Wallet::payPal();
    }

    public static function buttonStyles(): array
    {
        return self::BUTTON_STYLES;
    }

    public static function buttonTypes(): array
    {
        return self::BUTTON_TYPES;
    }

    /**
     * PayPal shops existed long before the express button, so it is only
     * shown on the pages the merchant selected.
     */
    public function getPlacements(): array
    {
        if (!array_key_exists(self::KEY_SHOW_ON, $this->config)) {
            return [];
        }

        return parent::getPlacements();
    }

    /**
     * PayPal is always offered in the checkout; the express button comes on top.
     */
    public function isListedInCheckout(): bool
    {
        return true;
    }

    /**
     * Live payments need the merchant's PayPal merchant ID; in test mode
     * the sandbox merchant ID is optional.
     */
    public function isConfigured(): bool
    {
        return !$this->isLiveMode() || $this->getMerchantId() !== '';
    }

    /**
     * The merchant ID of the current mode, as entered (a collecting merchant
     * ID keeps its "c_" prefix).
     */
    public function getMerchantId(): string
    {
        return $this->getString($this->isLiveMode() ? self::KEY_MERCHANT_ID : self::KEY_SANDBOX_MERCHANT_ID);
    }

    /** The merchant ID PayPal's JS SDK is loaded with. */
    public function getSdkMerchantId(): string
    {
        $merchantId = $this->getMerchantId();

        return $this->isCollecting() ? substr($merchantId, strlen(self::COLLECTING_PREFIX)) : $merchantId;
    }

    public function getClientId(): string
    {
        if ($this->isCollecting()) {
            return $this->isLiveMode() ? self::COLLECTING_CLIENT_ID_LIVE : self::COLLECTING_CLIENT_ID_TEST;
        }

        return $this->isLiveMode() ? self::CLIENT_ID_LIVE : self::CLIENT_ID_TEST;
    }

    public function getPartnerAttributionId(): string
    {
        return $this->isCollecting() ? self::COLLECTING_PARTNER_ATTRIBUTION_ID : self::PARTNER_ATTRIBUTION_ID;
    }

    /** Buckaroo checkout of the current mode, which creates the PayPal order. */
    public function getCheckoutUrl(): string
    {
        return $this->isLiveMode() ? self::CHECKOUT_URL_LIVE : self::CHECKOUT_URL_TEST;
    }

    public function isButtonRounded(): bool
    {
        return self::isYes($this->config[self::KEY_BUTTON_ROUNDED] ?? '0');
    }

    /** Seller protection is on unless the merchant switched it off. */
    public function isSellerProtectionEnabled(): bool
    {
        return self::isYes($this->config[self::KEY_SELLER_PROTECTION] ?? '1');
    }

    protected static function sanitizeCredentials(array $data): array
    {
        foreach ([self::KEY_MERCHANT_ID, self::KEY_SANDBOX_MERCHANT_ID] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = self::cleanCredential($data[$key], '/^(c_)?[A-Za-z0-9]{0,64}$/');
            }
        }

        foreach ([self::KEY_BUTTON_ROUNDED, self::KEY_SELLER_PROTECTION] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = self::isYes($data[$key]) ? '1' : '0';
            }
        }

        return $data;
    }

    private function isCollecting(): bool
    {
        return strpos($this->getMerchantId(), self::COLLECTING_PREFIX) === 0;
    }

    private function getString(string $key): string
    {
        $value = $this->config[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param mixed $value
     */
    private static function isYes($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
