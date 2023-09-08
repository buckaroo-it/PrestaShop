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

class GiftCard extends PaymentMethod
{
    public function __construct()
    {
        $this->type = 'giftcard';
        $this->mode = Config::getMode('GIFTCARD');
    }

    public function getPayload($data)
    {
        $payload = [
            'servicesSelectableByClient' => Config::get('BUCKAROO_GIFTCARD_ALLOWED_CARDS'),
            'continueOnIncomplete' => '1',
        ];

        return $payload;
    }

    public function pay($customVars = [])
    {
        $this->payload = $this->getPayload($customVars);

        return parent::executeCustomPayAction('payRedirect');
    }
}
