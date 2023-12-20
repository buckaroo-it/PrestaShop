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

if (!defined('_PS_VERSION_')) { exit; }

use Buckaroo\PrestaShop\Src\Service\BuckarooIdinService;

require_once dirname(__FILE__) . '/../response.php';
require_once dirname(__FILE__) . '/../../../library/logger.php';
class IdinResponse extends Response
{
    protected $buckarooIdinService;

    public function __construct($transactionResponse = null)
    {
        $this->buckarooIdinService = new BuckarooIdinService();
        $this->parsePostResponseChild();
        parent::__construct($transactionResponse);
    }

    protected function parsePostResponseChild()
    {
        if ($customerId = \Tools::getValue('ADD_cid')) {
            if ($consumerbin = \Tools::getValue('brq_SERVICE_idin_ConsumerBIN')) {
                if ($iseighteenorolder = \Tools::getValue('brq_SERVICE_idin_IsEighteenOrOlder')) {
                    if ($this->buckarooIdinService->checkCustomerIdExists($customerId)) {
                        $this->buckarooIdinService->updateCustomerData($customerId, $consumerbin, $iseighteenorolder);
                    } else {
                        $this->buckarooIdinService->insertCustomerData($customerId, $consumerbin, $iseighteenorolder);
                    }
                }
            }
        }
    }
}
