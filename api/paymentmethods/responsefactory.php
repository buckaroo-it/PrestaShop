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

require_once dirname(__FILE__) . '/buckaroopaypal/paypalresponse.php';
require_once dirname(__FILE__) . '/ideal/idealresponse.php';
require_once dirname(__FILE__) . '/idin/idinresponse.php';
require_once dirname(__FILE__) . '/transfer/transferresponse.php';
require_once dirname(__FILE__) . '/creditcard/creditcardresponse.php';
require_once dirname(__FILE__) . '/giftcard/giftcardresponse.php';
require_once dirname(__FILE__) . '/responsedefault.php';

class ResponseFactory
{
    final private static function getPaymentMethod($data = null)
    {
        $paymentMethod = 'default';

        if (!is_null($data) && ($data[0] != false)) {
            if (!empty($data[0]->ServiceCode)) {
                $paymentMethod = $data[0]->ServiceCode;
            }
        } else {
            if (Tools::getValue('brq_payment_method')) {
                $paymentMethod = Tools::getValue('brq_payment_method');
            }elseif (Tools::getValue('brq_primary_service')) {
                $paymentMethod = Tools::getValue('brq_primary_service');
            } else {
                if (Tools::getValue('brq_transaction_method')) {
                    $paymentMethod = Tools::getValue('brq_transaction_method');
                }
            }
        }
        return $paymentMethod;
    }

    //If $data is not null - SOAP response, otherwise HTTP response
    final public static function getResponse($data = null)
    {
        $paymentmethod = self::getPaymentMethod($data);

        switch ($paymentmethod) {
            case 'paypal':
                return new PayPalResponse($data);
            case 'ideal':
                return new IdealResponse($data);
            case 'transfer':
                return new TransferResponse($data);
            case 'IDIN':
                return new IdinResponse($data);
            default:
                if (stripos(Config::get('BUCKAROO_CREDITCARD_CARDS'), $paymentmethod) !== false) {
                    return new CreditCardResponse($data);
                } else {
                    if (stripos(Config::get('BUCKAROO_GIFTCARD_CARDS'), $paymentmethod) !== false) {
                        return new GiftCardResponse($data);
                    } else {
                        return new ResponseDefault($data);
                    }
                }
                break;
        }
    }
}
