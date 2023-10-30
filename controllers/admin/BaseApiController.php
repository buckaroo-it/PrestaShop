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

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;

class BaseApiController extends FrameworkBundleAdminController
{
    protected function sendResponse($data, $status = 200)
    {
        return new JsonResponse($data, $status);
    }

    protected function sendErrorResponse($message, $status = 400)
    {
        return $this->sendResponse(['error' => $message], $status);
    }

    protected function getJsonInput(): array
    {
        $rawData = \Tools::file_get_contents('php://input');

        return json_decode($rawData, true);
    }
}
