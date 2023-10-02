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

class BuckarooSettingsService
{
    public const BUCKAROO_MERCHANT_KEY = 'BUCKAROO_MERCHANT_KEY';
    public const BUCKAROO_SECRET_KEY = 'BUCKAROO_SECRET_KEY';
    public const BUCKAROO_TRANSACTION_LABEL = 'BUCKAROO_TRANSACTION_LABEL';
    public const BUCKAROO_TEST = 'BUCKAROO_TEST';

    public function getSettings()
    {
        return [
            'is_enabled' => 1,
            'is_live' => (int) \Configuration::get(self::BUCKAROO_TEST),
            'website_key' => \Configuration::get(self::BUCKAROO_MERCHANT_KEY),
            'secret_key' => \Configuration::get(self::BUCKAROO_SECRET_KEY),
            'transaction_description' => \Configuration::get(self::BUCKAROO_TRANSACTION_LABEL),
            'refund_enabled' => true,
            'refund_label' => null,
        ];
    }

    public function isValidData($data)
    {
        $requiredKeys = ['website_key', 'secret_key', 'transaction_description', 'is_live'];
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key])) {
                return false;
            }
        }

        return true;
    }

    public function updateSettings($data)
    {
        \Configuration::updateValue(self::BUCKAROO_MERCHANT_KEY, $data['website_key']);
        \Configuration::updateValue(self::BUCKAROO_SECRET_KEY, $data['secret_key']);
        \Configuration::updateValue(self::BUCKAROO_TRANSACTION_LABEL, $data['transaction_description']);
        \Configuration::updateValue(self::BUCKAROO_TEST, $data['is_live']);
    }
}
