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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class BaseApiController extends FrameworkBundleAdminController
{
    protected function getCsrfTokenManager(): ?CsrfTokenManagerInterface
    {
        try {
            if ($this->has('security.csrf.token_manager')) {
                return $this->get('security.csrf.token_manager');
            }
            
            if ($this->has('buckaroo.csrf.token_manager')) {
                return $this->get('buckaroo.csrf.token_manager');
            }
        } catch (\Exception $e) {
            // Service not available
        }
        
        return null;
    }

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
        
        if (empty($rawData)) {
            return [];
        }
        
        $data = json_decode($rawData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON input: ' . json_last_error_msg());
        }
        
        return $data ?? [];
    }
}
