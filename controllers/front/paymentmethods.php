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
                ['id' => 1, 'name' => 'ideal', 'icon' => 'iDEAL.svg', 'mode' => 'test'],
                ['id' => 2, 'name' => 'paybybank', 'icon' => 'paybybank.gif', 'mode' => 'test'],
                ['id' => 3, 'name' => 'paypal', 'icon' => 'PayPal.svg', 'mode' => 'test'],
                ['id' => 4, 'name' => 'sepadirectdebit', 'icon' => 'SEPA-directdebit.svg', 'mode' => 'test'],
                ['id' => 5, 'name' => 'giropay', 'icon' => 'Giropay.svg', 'mode' => 'test'],
                ['id' => 6, 'name' => 'kbc', 'icon' => 'KBC.svg', 'mode' => 'test'],
                ['id' => 7, 'name' => 'bancontact', 'icon' => 'Bancontact.svg', 'mode' => 'test'],
                ['id' => 8, 'name' => 'giftcard', 'icon' => 'Giftcards.svg', 'mode' => 'test'],
                ['id' => 9, 'name' => 'creditcard', 'icon' => 'Creditcards.svg', 'mode' => 'test'],
                ['id' => 10, 'name' => 'sofort', 'icon' => 'Sofort.svg', 'mode' => 'test'],
                ['id' => 11, 'name' => 'belfius', 'icon' => 'Belfius.svg', 'mode' => 'test'],
                ['id' => 12, 'name' => 'afterpay', 'icon' => 'AfterPay.svg', 'mode' => 'test'],
                ['id' => 13, 'name' => 'klarnakp', 'icon' => 'Klarna.svg', 'mode' => 'off'],
                ['id' => 14, 'name' => 'applepay', 'icon' => 'ApplePay.svg', 'mode' => 'test'],
                ['id' => 15, 'name' => 'in3', 'icon' => 'In3.svg', 'mode' => 'test'],
                ['id' => 16, 'name' => 'billink', 'icon' => 'billink.svg', 'mode' => 'test'],
                ['id' => 17, 'name' => 'eps', 'icon' => 'eps.svg', 'mode' => 'test'],
                ['id' => 18, 'name' => 'przelewy24', 'icon' => 'przelewy24.svg', 'mode' => 'test'],
                ['id' => 19, 'name' => 'payperemail', 'icon' => 'payperemail.svg', 'mode' => 'test'],
                ['id' => 20, 'name' => 'payconiq', 'icon' => 'Payconiq.svg', 'mode' => 'test'],
                ['id' => 21, 'name' => 'tinka', 'icon' => 'tinka.svg', 'mode' => 'test'],
                ['id' => 22, 'name' => 'trustly', 'icon' => 'Trustly.svg', 'mode' => 'test']
            ]
        ];

        $this->sendResponse($data);
    }
}
