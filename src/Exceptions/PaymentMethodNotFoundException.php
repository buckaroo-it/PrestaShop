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

namespace Buckaroo\PrestaShop\Src\Exceptions;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Thrown when a payment request or checkout is asked for a payment method the
 * module does not implement.
 */
class PaymentMethodNotFoundException extends \RuntimeException
{
    /** @var string */
    private $paymentMethod;

    public function __construct(string $paymentMethod, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Payment method "%s" not found', $paymentMethod), 1, $previous);
        $this->paymentMethod = $paymentMethod;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }
}
