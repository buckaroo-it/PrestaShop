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

use Buckaroo\PrestaShop\Src\Config\Config;

/**
 * Collects the shop-level fields of a report batch from PrestaShop.
 * Every lookup is defensive: missing data never prevents a report.
 */
class ShopMetadataProvider
{
    /**
     * @param string|null $paymentMethod payment method of the failing request, when known
     *
     * @return array
     */
    public function getMetadata(?string $paymentMethod = null): array
    {
        $metadata = [
            'plugin_version' => $this->getPluginVersion(),
            'platform_version' => defined('_PS_VERSION_') ? (string) _PS_VERSION_ : 'unknown',
            'php_version' => PHP_VERSION,
            'environment' => $this->getEnvironment($paymentMethod),
            'shop_url' => $this->getShopUrl(),
        ];

        $websiteKey = $this->getWebsiteKey();
        if ($websiteKey !== '') {
            $metadata['website_key'] = $websiteKey;
        }

        return $metadata;
    }

    public function getPluginVersion(): string
    {
        if (class_exists('Buckaroo3', false) && defined('Buckaroo3::MODULE_VERSION')) {
            return (string) \Buckaroo3::MODULE_VERSION;
        }

        try {
            if (class_exists('Module')) {
                $module = \Module::getInstanceByName('buckaroo3');
                if ($module && !empty($module->version)) {
                    return (string) $module->version;
                }
            }
        } catch (\Throwable $e) {
            // Fall through.
        }

        return 'unknown';
    }

    /**
     * Buckaroo mode is configured per payment method. Use the mode of the
     * failing payment method when known, otherwise "live" as soon as any
     * method is live.
     */
    public function getEnvironment(?string $paymentMethod = null): string
    {
        try {
            if ($paymentMethod !== null && $paymentMethod !== '' && class_exists('Buckaroo\PrestaShop\Classes\Config')) {
                return \Buckaroo\PrestaShop\Classes\Config::getMode($paymentMethod) === 'live' ? 'live' : 'test';
            }

            if (class_exists('Db') && defined('_DB_PREFIX_')) {
                $liveCount = (int) \Db::getInstance()->getValue(
                    'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'bk_configuration` WHERE `value` LIKE \'%"mode":"live"%\''
                );

                return $liveCount > 0 ? 'live' : 'test';
            }
        } catch (\Throwable $e) {
            // Fall through.
        }

        return 'test';
    }

    public function getShopUrl(): string
    {
        try {
            if (class_exists('Context')) {
                $context = \Context::getContext();
                if (isset($context->shop) && is_object($context->shop) && method_exists($context->shop, 'getBaseURL')) {
                    $url = (string) $context->shop->getBaseURL(true);
                    if ($url !== '') {
                        return $url;
                    }
                }
            }
            if (class_exists('Tools') && method_exists('Tools', 'getShopDomainSsl')) {
                return (string) \Tools::getShopDomainSsl(true);
            }
        } catch (\Throwable $e) {
            // Fall through.
        }

        return '';
    }

    public function getWebsiteKey(): string
    {
        try {
            return trim((string) \Configuration::get(Config::BUCKAROO_MERCHANT_KEY));
        } catch (\Throwable $e) {
            return '';
        }
    }
}
