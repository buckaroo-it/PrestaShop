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
include dirname(__FILE__) . '/BaseApiController.php';
use Buckaroo\BuckarooClient;
class Buckaroo3ApiModuleFrontController extends BaseApiController
{
    public function initContent()
    {
        parent::initContent();
    }

    public function postProcess()
    {
        header('Content-Type: application/json');

        if (empty(Tools::getValue('website_key')) || empty(Tools::getValue('secret_key'))) {
            $this->ajaxDie(json_encode([
                'status' => false,
                'message' => 'Missing website_key or secret_key'
            ]));
        }

        $buckarooClient = new BuckarooClient(Tools::getValue('website_key'), Tools::getValue('secret_key'));
        $status = $buckarooClient->confirmCredential();

        $this->ajaxDie(json_encode(['status' => $status]));
    }

}