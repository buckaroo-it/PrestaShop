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

use Buckaroo\PrestaShop\Src\Config\Config;

class BuckarooSettingsService
{
    public function getSettings()
    {
        return [
            'is_enabled' => 1,
            'is_live' => (int) \Configuration::get(Config::BUCKAROO_TEST),
            'website_key' => \Configuration::get(Config::BUCKAROO_MERCHANT_KEY),
            'secret_key' => \Configuration::get(Config::BUCKAROO_SECRET_KEY),
            'transaction_description' => \Configuration::get(Config::BUCKAROO_TRANSACTION_LABEL),
            'restock' => (bool) \Configuration::get(Config::LABEL_REFUND_RESTOCK),
            'creditSlip' => (bool) \Configuration::get(Config::LABEL_REFUND_CREDIT_SLIP),
            'voucher' => (bool) \Configuration::get(Config::LABEL_REFUND_VOUCHER),
            'negativePayment' => (bool) \Configuration::get(Config::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT),
            'refund_label' => null,
        ];
    }

    public function isValidData($data)
    {
        $requiredKeys = ['website_key', 'secret_key', 'transaction_description', 'is_live', 'restock', 'creditSlip', 'voucher', 'negativePayment'];
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key])) {
                return false;
            }
        }

        return true;
    }

    public function updateSettings($data)
    {
        \Configuration::updateValue(Config::BUCKAROO_MERCHANT_KEY, $data['website_key']);
        \Configuration::updateValue(Config::BUCKAROO_SECRET_KEY, $data['secret_key']);
        \Configuration::updateValue(Config::BUCKAROO_TRANSACTION_LABEL, $data['transaction_description']);
        \Configuration::updateValue(Config::BUCKAROO_TEST, $data['is_live']);
        \Configuration::updateValue(Config::LABEL_REFUND_RESTOCK, $data['restock']);
        \Configuration::updateValue(Config::LABEL_REFUND_CREDIT_SLIP, $data['creditSlip']);
        \Configuration::updateValue(Config::LABEL_REFUND_VOUCHER, $data['voucher']);
        \Configuration::updateValue(Config::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT, $data['negativePayment']);
    }
}
