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

class PayPerEmail extends PaymentMethod
{
    /**
     * @var BuckarooConfigService
     */
    protected $buckarooConfigService;

    /** @var Buckaroo3 */
    public $module;

    public function __construct()
    {
        $this->type = 'payperemail';
        $this->version = '1';
        $this->mode = $this->getMode($this->type);
    }

    public function pay($customVars = [])
    {
        $this->payload = $this->getPayload($customVars);

        return parent::executeCustomPayAction('paymentInvitation');
    }

    public function getPayload($data)
    {
        return array_merge_recursive($this->payload, $data);
    }
}
