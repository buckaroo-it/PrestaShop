<?php
/**
*
*
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

include_once(_PS_MODULE_DIR_ . 'buckaroo3/controllers/front/common.php');

class Buckaroo3ErrorModuleFrontController extends BuckarooCommonController
{

    public $ssl = true;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $cookie = new Cookie('ps');
        $this->display_column_left = false;
        $this->display_column_right = false;
        parent::initContent();
        $error = Tools::getValue('error', null);
        if ($cookie->statusMessage != '') {
            $error = $cookie->statusMessage;
        }
        $invoice = Tools::getValue('invoice', null);
        $this->displayError($invoice, $error);

    }
}
