<?php
include_once dirname(__FILE__) . '/BaseApiController.php';

class Buckaroo3PaymentmethodconfigModuleFrontController extends BaseApiController
{

//    public function initContent()
//    {
//        parent::initContent();
//
//        $data = [
//            "status" => true,
//            "config" => [
//                "id" => 8,
//                "value" => [
//                    "mode" => "test"
//                ]
//            ]
//        ];
//
//        $this->sendResponse($data);
//    }

    public function postProcess()
    {
        header('Content-Type: application/json');

        if ($_POST) {
            if($_POST['name'] == 'bancontact') {
                Configuration::updateValue('BUCKAROO_MISTERCASH_MODE', $_POST['mode']);
            }else if($_POST['name'] == 'sofort') {
                Configuration::updateValue('BUCKAROO_SOFORTBANKING_MODE', $_POST['mode']);
            }else if($_POST['name'] == 'sepadirectdebit') {
                Configuration::updateValue('BUCKAROO_SDD_MODE', $_POST['mode']);
            }else{
                Configuration::updateValue('BUCKAROO_'.strtoupper($_POST['name']).'_MODE', $_POST['mode']);
            }

            return [
                'status' => true,
            ];
        }
    }
}
