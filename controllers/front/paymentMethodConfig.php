<?php
include dirname(__FILE__) . '/BaseApiController.php';

use Buckaroo\Prestashop\Service\PaymentMethodConfigService;
class Buckaroo3PaymentMethodConfigModuleFrontController extends BaseApiController
{
    private $paymentService;

    public function __construct()
    {
        parent::__construct();
        $this->paymentService = new PaymentMethodConfigService();
    }
    public function initContent()
    {
        parent::initContent();
        $this->authenticate();


        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $this->handleGet();
                break;
            case 'POST':
                $this->handlePost();
                break;
        }
    }

    private function handleGet(){

        // Fetch the parameter from the GET request
        $paymentName = Tools::getValue('paymentName');

        if (!$paymentName) {
            $this->sendErrorResponse("Payment name is missing.", 400);
            return;
        }

        $data = [
            "status" => true,
            "config" => [
                "value" => $this->paymentService->getPaymentConfig($paymentName)
            ]
        ];

        $this->sendResponse($data);
    }

    private function handlePost(){
        $data = $this->getJsonInput();

        $paymentName = Tools::getValue('paymentName');
        if (!$paymentName || !$data) {
            $this->sendErrorResponse("Invalid data provided.", 400);
            return;
        }
        $this->paymentService->updatePaymentConfig($paymentName, $data);
        $this->sendResponse(['status' => true]);
    }
}
