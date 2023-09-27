<?php
/**
     * NOTICE OF LICENSE
     *
     * This source file is subject to the Academic Free License (AFL 3.0)
     * It is available through the world-wide-web at this URL:
     * http://opensource.org/licenses/afl-3.0.php
     *
     * DISCLAIMER
     *
     * Do not edit or add to this file if you wish to upgrade this file
     *
     *  @author    Buckaroo.nl <plugins@buckaroo.nl>
     *  @copyright Copyright (c) Buckaroo B.V.
     *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
     */

include_once dirname(__FILE__) . '/BaseApiController.php';
use Buckaroo\Src\Repository\PaymentMethodRepository;

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
