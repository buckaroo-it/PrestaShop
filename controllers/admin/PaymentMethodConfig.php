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
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentMethodConfig extends BaseApiController
{
    private ?BuckarooConfigService $buckarooConfigService = null;

    /**
     * Get the Buckaroo config service using proper DI pattern
     * Lazy-loads the service only when needed and caches it
     */
    private function getBuckarooConfigService(): BuckarooConfigService
    {
        if ($this->buckarooConfigService === null) {
            try {
                // Use DI to get the service, or fallback to instantiation if not available
                $this->buckarooConfigService = $this->has('buckaroo.config.api.config.service') ? 
                    $this->get('buckaroo.config.api.config.service') : new BuckarooConfigService();
            } catch (\Exception $e) {
                // Container or service not available, fallback to direct instantiation
                $this->buckarooConfigService = new BuckarooConfigService();
            }
        }
        
        return $this->buckarooConfigService;
    }

    public function initContent()
    {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                return $this->handleGet();
            case 'POST':
                return $this->handlePost();
        }
    }

    private function handleGet()
    {
        $paymentName = \Tools::getValue('paymentName');

        if (!$paymentName) {
            return $this->sendErrorResponse('Payment name is missing.', 400);
        }

        $data = [
            'status' => true,
            'config' => [
                'value' => $this->getBuckarooConfigService()->getConfigArrayForMethod($paymentName),
            ],
        ];

        return $this->sendResponse($data);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function handlePost()
    {
        $data = $this->getJsonInput();

        $paymentName = \Tools::getValue('paymentName');
        if (!$paymentName || !$data) {
            return $this->sendErrorResponse('Invalid data provided.', 400);
        }
        $result = $this->getBuckarooConfigService()->updatePaymentMethodConfig($paymentName, $data);

        return $this->sendResponse(['status' => $result]);
    }
}
