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

class ClickToPay extends PaymentMethod
{
    public function __construct()
    {
        $this->type = 'clicktopay';
        $this->version = 1;
    }

    /**
     * The Drop-in UI authenticates the shopper in the browser and hands back an
     * identifier plus an encrypted transient token. Both are required service
     * parameters for the server-to-server Pay call.
     */
    public function pay($customVars = [])
    {
        $identifier = isset($customVars['identifier']) ? trim((string) $customVars['identifier']) : '';
        $transientToken = isset($customVars['transientToken']) ? trim((string) $customVars['transientToken']) : '';

        if ($transientToken === '' || $identifier === '') {
            throw new Exception('Please complete the Click to Pay checkout before continuing with payment.');
        }

        $this->payload['identifier'] = $identifier;
        $this->payload['transientToken'] = $transientToken;

        return parent::pay();
    }
}
