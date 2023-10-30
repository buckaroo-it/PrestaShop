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

namespace Buckaroo\PrestaShop\Controllers\admin;

use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;

class PaymentMethodConfig extends BaseApiController
{
    private BuckarooConfigService $buckarooConfigService;

    public function __construct(BuckarooConfigService $buckarooConfigService)
    {
        $this->buckarooConfigService = $buckarooConfigService;
    }

    public function initContent()
    {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                return $this->handleGet();
                break;
            case 'POST':
                return $this->handlePost();
                break;
        }
    }

    private function handleGet()
    {
        // Fetch the parameter from the GET request
        $paymentName = \Tools::getValue('paymentName');

        if (!$paymentName) {
            return $this->sendErrorResponse('Payment name is missing.', 400);
        }

        $data = [
            'status' => true,
            'config' => [
                'value' => $this->buckarooConfigService->getConfigArrayForMethod($paymentName),  // Call the repository to fetch the data
            ],
        ];

        return $this->sendResponse($data);
    }

    private function handlePost()
    {
        $data = $this->getJsonInput();

        $paymentName = \Tools::getValue('paymentName');
        if (!$paymentName || !$data) {
            return $this->sendErrorResponse('Invalid data provided.', 400);
        }
        $result = $this->buckarooConfigService->updatePaymentMethodConfig($paymentName, $data);  // Call the repository to update the data

        return $this->sendResponse(['status' => $result]);
    }
}
