<?php
include_once dirname(__FILE__) . '/BaseApiController.php';

class Buckaroo3PaymentmethodsModuleFrontController extends BaseApiController
{
    public function initContent()
    {
        parent::initContent();

        $data = $this->getAllPaymentMethods();

        $this->sendResponse($data);
    }

    public function getAllPaymentMethods()
    {
        $payments = [
            ['id' => 1, 'name' => 'ideal', 'icon' => 'iDEAL.svg', 'mode' => Config::get('BUCKAROO_IDEAL_MODE')],
            ['id' => 2, 'name' => 'paybybank', 'icon' => 'paybybank.gif', 'mode' => Config::get('BUCKAROO_PAYBYBANK_MODE')],
            ['id' => 3, 'name' => 'paypal', 'icon' => 'PayPal.svg', 'mode' => Config::get('BUCKAROO_PAYPAL_MODE')],
            ['id' => 4, 'name' => 'sepadirectdebit', 'icon' => 'SEPA-directdebit.svg', 'mode' => Config::get('BUCKAROO_SDD_MODE')],
            ['id' => 5, 'name' => 'giropay', 'icon' => 'Giropay.svg', 'mode' => Config::get('BUCKAROO_GIROPAY_MODE')],
            ['id' => 6, 'name' => 'kbc', 'icon' => 'KBC.svg', 'mode' => Config::get('BUCKAROO_KBC_MODE')],
            ['id' => 7, 'name' => 'bancontact', 'icon' => 'Bancontact.svg', 'mode' => Config::get('BUCKAROO_MISTERCASH_MODE')],
            ['id' => 8, 'name' => 'giftcard', 'icon' => 'Giftcards.svg', 'mode' => Config::get('BUCKAROO_GIFTCARD_MODE')],
            ['id' => 9, 'name' => 'creditcard', 'icon' => 'Creditcards.svg', 'mode' => Config::get('BUCKAROO_CREDITCARD_MODE')],
            ['id' => 10, 'name' => 'sofort', 'icon' => 'Sofort.svg', 'mode' => Config::get('BUCKAROO_SOFORTBANKING_MODE')],
            ['id' => 11, 'name' => 'belfius', 'icon' => 'Belfius.svg', 'mode' => Config::get('BUCKAROO_BELFIUS_MODE')],
            ['id' => 12, 'name' => 'afterpay', 'icon' => 'AfterPay.svg', 'mode' => Config::get('BUCKAROO_AFTERPAY_MODE')],
            ['id' => 13, 'name' => 'klarna', 'icon' => 'Klarna.svg', 'mode' => Config::get('BUCKAROO_KLARNA_MODE')],
            ['id' => 14, 'name' => 'applepay', 'icon' => 'ApplePay.svg', 'mode' => Config::get('BUCKAROO_APPLEPAY_MODE')],
            ['id' => 15, 'name' => 'in3', 'icon' => 'In3.svg', 'mode' => Config::get('BUCKAROO_IN3_MODE')],
            ['id' => 16, 'name' => 'billink', 'icon' => 'billink.svg', 'mode' => Config::get('BUCKAROO_BILLINK_MODE')],
            ['id' => 17, 'name' => 'eps', 'icon' => 'eps.svg', 'mode' => Config::get('BUCKAROO_EPS_MODE')],
            ['id' => 18, 'name' => 'przelewy24', 'icon' => 'przelewy24.svg', 'mode' => Config::get('BUCKAROO_PRZELEWY24_MODE')],
            ['id' => 19, 'name' => 'payperemail', 'icon' => 'payperemail.svg', 'mode' => Config::get('BUCKAROO_PAYPEREMAIL_MODE')],
            ['id' => 20, 'name' => 'payconiq', 'icon' => 'Payconiq.svg', 'mode' => Config::get('BUCKAROO_PAYCONIQ_MODE')],
            ['id' => 21, 'name' => 'tinka', 'icon' => 'tinka.svg', 'mode' => Config::get('BUCKAROO_TINKA_MODE')],
            ['id' => 22, 'name' => 'trustly', 'icon' => 'Trustly.svg', 'mode' => Config::get('BUCKAROO_TRUSTLY_MODE')]
        ];

        $data = [
            'status' => true,
            'payments' => $payments
        ];

        return $data;
    }

}
