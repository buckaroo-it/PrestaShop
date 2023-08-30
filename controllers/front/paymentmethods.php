<?php
include_once dirname(__FILE__) . '/BaseApiController.php';

class Buckaroo3PaymentmethodsModuleFrontController extends BaseApiController
{
    public function initContent()
    {
        parent::initContent();

        $data = [
            'status' => true,
            'payments' => [
                ['id' => 11, 'name' => 'afterpay', 'icon' => 'AfterPay.svg', 'mode' => 'test'],
                ['id' => 1, 'name' => 'applepay', 'icon' => 'ApplePay.svg', 'mode' => 'test'],
                ['id' => 6, 'name' => 'bancontact', 'icon' => 'Bancontact.svg', 'mode' => 'test'],
                ['id' => 13, 'name' => 'belfius', 'icon' => 'Belfius.svg', 'mode' => 'test'],
                ['id' => 2, 'name' => 'creditcard', 'icon' => 'Creditcards.svg', 'mode' => 'test'],
                ['id' => 3, 'name' => 'giftcard', 'icon' => 'Giftcards.svg', 'mode' => 'test'],
                ['id' => 8, 'name' => 'giropay', 'icon' => 'Giropay.svg', 'mode' => 'test'],
                ['id' => 4, 'name' => 'ideal', 'icon' => 'iDEAL.svg', 'mode' => 'test'],
                ['id' => 10, 'name' => 'klarnakp', 'icon' => 'Klarna.svg', 'mode' => 'off'],
                ['id' => 9, 'name' => 'payconiq', 'icon' => 'Payconiq.svg', 'mode' => 'test'],
                ['id' => 5, 'name' => 'paypal', 'icon' => 'PayPal.svg', 'mode' => 'test'],
                ['id' => 7, 'name' => 'sofort', 'icon' => 'Sofort.svg', 'mode' => 'test'],
                ['id' => 12, 'name' => 'trustly', 'icon' => 'Trustly.svg', 'mode' => 'test']
            ]
        ];

        $this->sendResponse($data);
    }
}
