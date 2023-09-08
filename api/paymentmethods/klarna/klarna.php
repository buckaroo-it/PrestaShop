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
require_once dirname(__FILE__) . '/../paymentmethod.php';

class Klarna extends PaymentMethod
{
    public function __construct()
    {
        $this->type = 'klarnakp';
        $this->version = '0';
        $this->mode = Config::getMode('KLARNA');
    }

    // @codingStandardsIgnoreStart
    public function pay($customVars = [])
    {
        // @codingStandardsIgnoreEnd
        return null;
    }

    public function getPayload($data)
    {
        return array_merge_recursive($this->payload, $data);
    }

    // @codingStandardsIgnoreStart
    public function payKlarna($customVars = [])
    {
        $this->payload = $this->getPayload($customVars);

        return parent::executeCustomPayAction('reserve');
    }
}
