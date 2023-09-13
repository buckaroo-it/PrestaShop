<?php
include_once dirname(__FILE__) . '/BaseApiController.php';

class Buckaroo3PaymentMethodConfigModuleFrontController extends BaseApiController
{
    public function initContent()
    {
        parent::initContent();

        $data = [
            "status" => true,
            "config" => [
                "id" => 8,
                "value" => [
                    "mode" => "test"
                ]
            ]
        ];

        $this->sendResponse($data);
    }

    public function postProcess()
    {
        header('Content-Type: application/json');

        if ($_POST) {
            var_dump($_POST['mode']);
            var_dump($_POST['name']);
//            Configuration::updateValue('BUCKAROO_IS_ENABLED', $_POST['is_enabled']);
//            Configuration::updateValue('BUCKAROO_MERCHANT_KEY', $_POST['website_key']);
//            Configuration::updateValue('BUCKAROO_SECRET_KEY', $_POST['secret_key']);
//            Configuration::updateValue('BUCKAROO_TRANSACTION_LABEL', $_POST['transaction_description']);
//            Configuration::updateValue('BUCKAROO_TEST', $_POST['is_live']);
        }

    }
}
