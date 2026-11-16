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

use Buckaroo\PrestaShop\Src\Express\AbstractExpressSettings;
use Buckaroo\PrestaShop\Src\Express\Wallet;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Apple Pay express settings, read from the Apple Pay method configuration
 * (the same keys as the WooCommerce plugin).
 */
final class ExpressSettings extends AbstractExpressSettings
{
    public const METHOD_NAME = Wallet::APPLE_PAY;

    /** @var string[] */
    public const BUTTON_STYLES = ['black', 'white', 'white-outline'];

    /** @var string[] */
    public const BUTTON_TYPES = ['plain', 'buy', 'check-out', 'order', 'book', 'subscribe'];

    public const KEY_MERCHANT_GUID = 'merchant_guid';

    public function getWallet(): Wallet
    {
        return Wallet::applePay();
    }

    public static function buttonStyles(): array
    {
        return self::BUTTON_STYLES;
    }

    public static function buttonTypes(): array
    {
        return self::BUTTON_TYPES;
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

    protected static function sanitizeCredentials(array $data): array
    {
        if (array_key_exists(self::KEY_MERCHANT_GUID, $data)) {
            $data[self::KEY_MERCHANT_GUID] = self::cleanCredential($data[self::KEY_MERCHANT_GUID], '/^[A-Za-z0-9-]{0,64}$/');
        }

        return $data;
    }
}
