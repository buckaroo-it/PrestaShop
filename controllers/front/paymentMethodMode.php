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

class Buckaroo3PaymentMethodModeModuleFrontController extends BaseApiController
{
    private BuckarooConfigService $buckarooConfigService;

    public function __construct()
    {
        parent::__construct();

        $this->buckarooConfigService = $this->module->getBuckarooConfigService();
    }

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

        $this->buckarooConfigService->updatePaymentMethodMode($data['name'], $data['mode']);
        $this->sendResponse(['status' => true]);
    }
}
