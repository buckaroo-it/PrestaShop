<?php

include_once dirname(__FILE__) . '/BaseApiController.php';
use Buckaroo\Prestashop\Repository\PaymentMethodRepository;

class Buckaroo3PaymentMethodsModuleFrontController extends BaseApiController
{
    private $paymentMethodRepository;

    public function __construct()
    {
        parent::__construct();

        $this->paymentMethodRepository = new PaymentMethodRepository();  // Instantiate the repository
    }

    public function initContent()
    {
        parent::initContent();
        $this->authenticate();

        $data = $this->getAllPaymentMethods();

        $this->sendResponse($data);
    }

    public function getAllPaymentMethods()
    {
        $payments = $this->getPaymentConfigurations();

        $data = [
            'status' => true,
            'payments' => $payments,
        ];

        return $data;
    }

    private function getPaymentConfigurations()
    {
        return $this->paymentMethodRepository->getPaymentMethodsFromDBWithConfig();  // Call the repository to fetch the data
    }
}
