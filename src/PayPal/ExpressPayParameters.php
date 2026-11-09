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

use Buckaroo\PaymentMethods\Paypal\Models\ExtraInfo;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Service parameters of an express payment: the approved PayPal order and,
 * when given, the seller protection data (customer name, address, phone).
 */
class ExpressPayParameters extends ExtraInfo
{
    /**
     * @var string
     */
    protected string $payPalOrderId;
}
