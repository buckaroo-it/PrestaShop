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
require_once dirname(__FILE__) . '/idin/idinresponse.php';
require_once dirname(__FILE__) . '/responsedefault.php';
require_once _PS_ROOT_DIR_ . '/modules/buckaroo3/vendor/autoload.php';

class ResponseFactory
{
    final public static function getResponse($transactionResponse = null)
    {
        $paymentmethod = null;

        if ($transactionResponse != null) {
            $data = $transactionResponse->data();

            if (isset($data['Services'][0]['Name'])) {
                $paymentmethod = $data['Services'][0]['Name'];
            } elseif (!empty($data['ServiceCode'])) {
                $paymentmethod = $data['ServiceCode'];
            }
        } elseif (Tools::isSubmit('brq_payment_method')) {
            $paymentmethod = Tools::getValue('brq_payment_method');
        } elseif (Tools::getValue('brq_primary_service')) {
            $paymentmethod = Tools::getValue('brq_primary_service');
        }
        if($paymentmethod === 'IDIN'){
            return new IdinResponse($transactionResponse);
        }

        return new ResponseDefault($transactionResponse);
        
    }
}
