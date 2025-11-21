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

if (!defined('_PS_VERSION_')) {
    exit;
}

class VerificationMethods extends BaseApiController
{
    private function getBuckarooConfigService(): BuckarooConfigService
    {
        try {
            if ($this->has('buckaroo.config.api.config.service')) {
                return $this->get('buckaroo.config.api.config.service');
            }
        } catch (\Exception $e) {
            // Container not available
        }
        
        return new BuckarooConfigService();
    }

    /**
     * @throws \Exception
     */
    public function initContent()
    {
        $data = $this->getAllPaymentMethods();

        return $this->sendResponse($data);
    }

    /**
     * @throws \Exception
     */
    public function getAllPaymentMethods()
    {
        $payments = $this->getPaymentConfigurations();

        return [
            'status' => true,
            'payments' => $payments,
        ];
    }

    /**
     * @throws \Exception
     */
    private function getPaymentConfigurations()
    {
        return $this->getBuckarooConfigService()->getVerificationMethodsFromDBWithConfig();
    }
}
