<?php
include_once dirname(__FILE__) . '/BaseApiController.php';


class Buckaroo3PaymentMethodConfigModuleFrontController extends BaseApiController
{
    public function initContent(int $paymentMethod)
    {
        parent::initContent();

        $data = [
            "status" => true,
            "config" => [
                "id" => 8,
                "channel_id" => $paymentMethod,
                "value" => [
                    "mode" => "test"
                ]
            ]
        ];

        $this->sendResponse($data);
    }
}
