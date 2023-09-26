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

namespace Buckaroo\Prestashop\Refund\Request;

use Buckaroo\BuckarooClient;
use Buckaroo\Transaction\Response\TransactionResponse;

class Handler
{
    /**
     * Execute refund request
     *
     * @param array  $body
     * @param string $method
     *
     * @return TransactionResponse
     */
    public function refund(array $body, string $method): TransactionResponse
    {
        $buckaroo = $this->getClient($method);

        return $buckaroo->method($method)->refund($body);
    }

    /**
     * Get buckaroo client
     *
     * @param string $method
     *
     * @return BuckarooClient
     */
    private function getClient(string $method): BuckarooClient
    {
        return new BuckarooClient(
            \Configuration::get('BUCKAROO_MERCHANT_KEY'),
            \Configuration::get('BUCKAROO_SECRET_KEY'),
            $this->getMode($method)
        );
    }

    /**
     * Get client mode
     *
     * @param string $method
     *
     * @return string
     */
    private function getMode(string $method): string
    {
        $key = \Tools::strtoupper($method);
        if (
            \Configuration::get('BUCKAROO_TEST') == 0
            && \Configuration::get('BUCKAROO_' . $key . '_TEST') == '0'
        ) {
            return 'live';
        }

        return 'test';
    }
}
