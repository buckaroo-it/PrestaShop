<?php
include dirname(__FILE__) . '/BaseApiController.php';

class Buckaroo3PaymentmethodmodeModuleFrontController extends BaseApiController
{
    private const PAYMENT_MAPPING = [
        'bancontact' => 'MISTERCASH',
        'sofort' => 'SOFORTBANKING',
        'sepadirectdebit' => 'SDD'
    ];
    public function initContent()
    {
        parent::initContent();
        $this->authenticate();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendErrorResponse('Invalid request method', 405); // 405: Method Not Allowed
            return;
        }

        $data = $this->getJsonInput();
        if (!isset($data['name'], $data['mode'])) {
            $this->sendErrorResponse('Required data not provided', 400); // 400: Bad Request
            return;
        }

        $this->updatePaymentMode($data['name'], $data['mode']);
        $this->sendResponse(['status' => true]);
    }

    private function updatePaymentMode($name, $mode)
    {
        $paymentName = $this->getPaymentConfigName($name);
        Configuration::updateValue('BUCKAROO_' . $paymentName . '_MODE', $mode);
    }

    private function getPaymentConfigName($name)
    {
        return self::PAYMENT_MAPPING[$name] ?? strtoupper($name);
    }
}
