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

namespace Buckaroo\PrestaShop\Src\PayPal;

use Buckaroo\PaymentMethods\Paypal\Paypal;
use Buckaroo\Transaction\Response\TransactionResponse;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The SDK's PayPal method with a payment for a PayPal order the shopper
 * already approved with the express button. The SDK sends the PayPal order
 * ID only with the Pay action and the seller protection data only with
 * ExtraInfo; with seller protection both go in one Pay,ExtraInfo request.
 */
class ExpressPayment extends Paypal
{
    /**
     * @return TransactionResponse
     */
    public function payExpress(bool $sellerProtection)
    {
        $this->setPayPayload();

        $this->setServiceList($sellerProtection ? 'Pay,ExtraInfo' : 'Pay', new ExpressPayParameters($this->payload));

        return $this->postRequest();
    }
}
