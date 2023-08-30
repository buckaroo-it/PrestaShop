<?php
if (!defined('_PS_VERSION_')) {
    return;
}

class Buckaroo3SettingsModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $data = [
            "status" => true,
            "settings"=> [
                "is_enabled"=> Configuration::get('BUCKAROO_IS_ENABLED'),
                "is_live"=> 0,
                "website_key"=> Configuration::get('BUCKAROO_MERCHANT_KEY'),
                "secret_key"=> Configuration::get('BUCKAROO_SECRET_KEY'),
                "display_discount_field"=> true,
                "transaction_description"=> Configuration::get('BUCKAROO_TRANSACTION_LABEL'),
                "refund_enabled"=> true,
                "refund_label"=> null,
                "return_url"=> 'http' . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . __PS_BASE_URI__ . 'index.php?fc=module&module=buckaroo3&controller=userreturn',
                "checkout_url"=> null,
                "response_url"=> null,
                "custom_scripts"=> []
            ]
        ];

        // Return as JSON
        header('Content-Type: application/json');
        die(json_encode($data));
    }

    public function postProcess()
    {
        header('Content-Type: application/json');

        if ($_POST) {
            var_dump('s');
            die(json_encode(['status' => true]));
        }

    }
}
