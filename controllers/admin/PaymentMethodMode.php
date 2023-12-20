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
use Symfony\Component\HttpFoundation\Request;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentMethodMode extends BaseApiController
{
    private BuckarooConfigService $buckarooConfigService;

    public function __construct(BuckarooConfigService $buckarooConfigService)
    {
        parent::__construct();
        $this->buckarooConfigService = $buckarooConfigService;
    }

    public function initContent(Request $request)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->sendErrorResponse('Invalid request method', 405); // 405: Method Not Allowed
        }

        $data = $this->getJsonInput();
        if (!isset($data['name'], $data['mode'])) {
            return $this->sendErrorResponse('Required data not provided', 400); // 400: Bad Request
        }

        $this->buckarooConfigService->updatePaymentMethodMode($data['name'], $data['mode']);

        return $this->sendResponse(['status' => true]);
    }
}
