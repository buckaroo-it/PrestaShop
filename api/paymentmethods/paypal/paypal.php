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

if (!defined('_PS_VERSION_')) { exit; }

require_once dirname(__FILE__) . '/../paymentmethod.php';

use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;

class PayPal extends PaymentMethod
{
    /**
     * @var BuckarooConfigService
     */
    protected $buckarooConfigService;

    public function __construct()
    {
        $this->type = 'paypal';
        $this->version = 1;

        $this->buckarooConfigService = \Module::getInstanceByName('buckaroo3')->getBuckarooConfigService();
    }

    // Seller protection payload
    public function getPayload($data)
    {
        return array_merge_recursive($this->payload, $data);
    }

    public function pay($customVars = [])
    {
        $sellerProtection = $this->buckarooConfigService->getConfigValue('paypal', 'seller_protection');
        if ($sellerProtection == '1') {
            // Pay with Seller Protection enabled
            $this->payload = $this->getPayload($customVars);

            return parent::executeCustomPayAction('extraInfo');
        } else {
            // Regular paypal payment
            return parent::pay();
        }
    }
}
