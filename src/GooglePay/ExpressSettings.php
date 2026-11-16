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

namespace Buckaroo\PrestaShop\Src\GooglePay;

use Buckaroo\PrestaShop\Src\Express\AbstractExpressSettings;
use Buckaroo\PrestaShop\Src\Express\Wallet;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Google Pay express settings, read from the Google Pay method configuration.
 * The credential keys are the ones the Google Pay method already stored.
 */
final class ExpressSettings extends AbstractExpressSettings
{
    public const METHOD_NAME = Wallet::GOOGLE_PAY;

    /** @var string[] */
    public const BUTTON_STYLES = ['black', 'white'];

    /**
     * Google's button types, "Pay with" (the default) first.
     *
     * @var string[]
     */
    public const BUTTON_TYPES = ['pay', 'plain', 'buy', 'checkout', 'order', 'book', 'subscribe'];

    /** Buckaroo gateway merchant ID (Buckaroo Plaza, Services > Google Pay). */
    public const KEY_GATEWAY_MERCHANT_ID = 'gatewayMerchantId';
    /** Google merchant ID (Google Pay & Wallet Console); required for live payments. */
    public const KEY_GOOGLE_MERCHANT_ID = 'merchantId';
    /** Name shown in the Google Pay sheet; the shop name when empty. */
    public const KEY_MERCHANT_NAME = 'merchantName';

    public function getWallet(): Wallet
    {
        return Wallet::googlePay();
    }

    public static function buttonStyles(): array
    {
        return self::BUTTON_STYLES;
    }

    public static function buttonTypes(): array
    {
        return self::BUTTON_TYPES;
    }

    public function getGatewayMerchantId(): string
    {
        return $this->getString(self::KEY_GATEWAY_MERCHANT_ID);
    }

    public function getGoogleMerchantId(): string
    {
        return $this->getString(self::KEY_GOOGLE_MERCHANT_ID);
    }

    public function getMerchantName(): string
    {
        return $this->getString(self::KEY_MERCHANT_NAME);
    }

    /**
     * Buckaroo needs its gateway merchant ID for the Google Pay token; Google
     * only requires its own merchant ID in production.
     */
    public function isConfigured(): bool
    {
        return $this->getGatewayMerchantId() !== ''
            && (!$this->isLiveMode() || $this->getGoogleMerchantId() !== '');
    }

    protected static function sanitizeCredentials(array $data): array
    {
        if (array_key_exists(self::KEY_GATEWAY_MERCHANT_ID, $data)) {
            $data[self::KEY_GATEWAY_MERCHANT_ID] = self::cleanCredential($data[self::KEY_GATEWAY_MERCHANT_ID], '/^[A-Za-z0-9._-]{0,100}$/');
        }

        if (array_key_exists(self::KEY_GOOGLE_MERCHANT_ID, $data)) {
            $data[self::KEY_GOOGLE_MERCHANT_ID] = self::cleanCredential($data[self::KEY_GOOGLE_MERCHANT_ID], '/^[A-Za-z0-9]{0,64}$/');
        }

        if (array_key_exists(self::KEY_MERCHANT_NAME, $data)) {
            $name = is_string($data[self::KEY_MERCHANT_NAME]) ? trim(strip_tags($data[self::KEY_MERCHANT_NAME])) : '';
            $data[self::KEY_MERCHANT_NAME] = function_exists('mb_substr') ? mb_substr($name, 0, 100) : substr($name, 0, 100);
        }

        return $data;
    }

    private function getString(string $key): string
    {
        $value = $this->config[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
