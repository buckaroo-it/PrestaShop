<?php

namespace Buckaroo\Src\Service;

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
            'display_discount_field' => true,
            'transaction_description' => \Configuration::get(self::BUCKAROO_TRANSACTION_LABEL),
            'refund_enabled' => true,
            'refund_label' => null,
            'return_url' => $this->getReturnUrl(),
            'checkout_url' => null,
            'response_url' => null,
            'custom_scripts' => [],
        ];
    }

    private function getReturnUrl()
    {
        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http');

        return $protocol . '://' . $_SERVER['SERVER_NAME'] . __PS_BASE_URI__ . 'index.php?fc=module&module=buckaroo3&controller=userreturn';
    }

    public function isValidData($data)
    {
        // Here you can perform more robust validation.
        // For simplicity, I'm checking the existence of required keys.
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