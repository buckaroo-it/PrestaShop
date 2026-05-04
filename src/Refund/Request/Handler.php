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

namespace Buckaroo\PrestaShop\Src\Refund\Request;

use Buckaroo\BuckarooClient;
use Buckaroo\PrestaShop\Classes\Config;
use Buckaroo\PrestaShop\Src\Refund\Settings;
use Buckaroo\Transaction\Response\TransactionResponse;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Handler
{
    /**
     * Execute refund request
     *
     * @param array $body
     * @param string $method
     *
     * @return TransactionResponse
     */
    public function refund(array $body, string $method): TransactionResponse
    {
        $normalizedMethod = PaymentMethodHelper::normalizeMethod($method);
        $buckaroo = $this->getClient($normalizedMethod);

        // For gift cards, use 'giftcard' as the method name for SDK call
        // The actual service code (e.g., 'boekenbon') should be in the payload's 'name' field
        $sdkMethod = $normalizedMethod;
        if (PaymentMethodHelper::isGiftCardMethod($normalizedMethod)) {
            $sdkMethod = 'giftcard';
        }

        return $buckaroo->method($sdkMethod)->refund($body);
    }

    /**
     * Get buckaroo client
     *
     * @param string $method
     *
     * @return BuckarooClient
     * @throws \Exception
     */
    private function getClient(string $method): BuckarooClient
    {
        $normalizedMethod = PaymentMethodHelper::normalizeMethod($method);

        // Normalize method for configuration lookup
        $configMethod = $normalizedMethod;
        if (PaymentMethodHelper::isCreditCardMethod($normalizedMethod)) {
            $configMethod = 'creditcard';
        } elseif (PaymentMethodHelper::isGiftCardMethod($normalizedMethod)) {
            // For gift cards, use 'giftcard' for configuration lookup
            // but keep the original service code for the actual refund API call
            $configMethod = 'giftcard';
        }

        return new BuckarooClient(
            \Configuration::get('BUCKAROO_MERCHANT_KEY'),
            \Configuration::get('BUCKAROO_SECRET_KEY'),
            Config::getMode($configMethod)
        );
    }
}
