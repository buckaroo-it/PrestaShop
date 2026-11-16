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
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../paymentmethod.php';

use Buckaroo\BuckarooClient;
use Buckaroo\PrestaShop\Src\PayPal\ExpressPayment;
use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;
use Buckaroo\Services\PayloadService;

class PayPal extends PaymentMethod
{
    private const ACTION_EXPRESS = 'payExpress';

    /**
     * @var BuckarooConfigService
     */
    protected $buckarooConfigService;

    /**
     * Send the seller protection data with the express payment.
     *
     * @var bool
     */
    private $expressSellerProtection = false;

    public function __construct()
    {
        $this->type = 'paypal';
        $this->version = 1;

        /** @var Buckaroo3 $module */
        $module = Module::getInstanceByName('buckaroo3');
        $this->buckarooConfigService = $module->getBuckarooConfigService();
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
        }

        // Regular paypal payment
        return parent::pay();
    }

    /**
     * Express checkout: pay the PayPal order the shopper approved with the
     * express button, instead of redirecting to PayPal.
     *
     * @param array<string, mixed> $sellerProtection customer, address and phone; empty without seller protection
     */
    public function payExpress(string $payPalOrderId, array $sellerProtection)
    {
        if ($payPalOrderId === '') {
            throw new Exception('The PayPal order was not received.');
        }

        $this->payload = array_merge($this->payload, $sellerProtection, ['payPalOrderId' => $payPalOrderId]);
        $this->expressSellerProtection = !empty($sellerProtection);

        return $this->payGlobal(self::ACTION_EXPRESS);
    }

    /**
     * @param BuckarooClient $buckaroo
     */
    protected function sendPayRequest($buckaroo, string $payAction)
    {
        if ($payAction !== self::ACTION_EXPRESS) {
            return parent::sendPayRequest($buckaroo, $payAction);
        }

        $payment = new ExpressPayment($buckaroo->client(), $this->type);
        $payment->setPayload((new PayloadService($this->payload))->toArray());

        return $payment->payExpress($this->expressSellerProtection);
    }
}
