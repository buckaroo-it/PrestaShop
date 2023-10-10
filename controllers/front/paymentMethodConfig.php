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

include dirname(__FILE__) . '/BaseApiController.php';

use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;

class Buckaroo3PaymentMethodConfigModuleFrontController extends BaseApiController
{
    private $buckarooConfigService;
    public $module;

    public function __construct()
    {
        parent::__construct();
        $this->buckarooConfigService = $this->module->getService(BuckarooConfigService::class);
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

    private function handleGet()
    {
        // Fetch the parameter from the GET request
        $paymentName = Tools::getValue('paymentName');

        if (!$paymentName) {
            $this->sendErrorResponse('Payment name is missing.', 400);

            return;
        }

        $data = [
            'status' => true,
            'config' => [
                'value' => $this->buckarooConfigService->getConfigArrayForMethod($paymentName),  // Call the repository to fetch the data
            ],
        ];

        $this->sendResponse($data);
    }

    private function handlePost()
    {
        $data = $this->getJsonInput();

        $paymentName = Tools::getValue('paymentName');
        if (!$paymentName || !$data) {
            $this->sendErrorResponse('Invalid data provided.', 400);

            return;
        }
        $result = $this->buckarooConfigService->updatePaymentMethodConfig($paymentName, $data);  // Call the repository to update the data
        $this->sendResponse(['status' => $result]);
    }
}
