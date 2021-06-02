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

include_once(dirname(__FILE__) . '/functions.php');


class PaymentRequestFactory
{

    const REQUEST_TYPE_PAYPAL = 'buckaroopaypal';
    const REQUEST_TYPE_EMPAYMENT = 'empayment';
    const REQUEST_TYPE_IDEAL = 'ideal';
    const REQUEST_TYPE_GIROPAY = 'giropay';
    const REQUEST_TYPE_KBC = 'kbc';
    const REQUEST_TYPE_SEPADIRECTDEBIT = 'sepadirectdebit';
    const REQUEST_TYPE_MISTERCASH = 'bancontactmrcash';
    const REQUEST_TYPE_SOFORTBANKING = 'sofortueberweisung';
    const REQUEST_TYPE_GIFTCARD = 'giftcard';
    const REQUEST_TYPE_CREDITCARD = 'creditcard';
    const REQUEST_TYPE_TRANSFER = 'transfer';
    const REQUEST_TYPE_AFTERPAY = 'afterpay';
    const REQUEST_TYPE_KLARNA = 'klarna';
    const REQUEST_TYPE_APPLEPAY = 'applepay';
    const REQUEST_TYPE_BELFIUS = 'belfius';
    const REQUEST_TYPE_IDIN = 'idin';

    // Request types (Payment Methods).
    static public $valid_request_types = array(
        PaymentRequestFactory::REQUEST_TYPE_PAYPAL => 'BuckarooPayPal',
        PaymentRequestFactory::REQUEST_TYPE_EMPAYMENT => 'Empayment',
        PaymentRequestFactory::REQUEST_TYPE_IDEAL => 'IDeal',
        PaymentRequestFactory::REQUEST_TYPE_SEPADIRECTDEBIT => 'SepaDirectDebit',
        PaymentRequestFactory::REQUEST_TYPE_GIROPAY => 'Giropay',
        PaymentRequestFactory::REQUEST_TYPE_KBC => 'Kbc',
        PaymentRequestFactory::REQUEST_TYPE_MISTERCASH => 'MisterCash',
        PaymentRequestFactory::REQUEST_TYPE_SOFORTBANKING => 'Sofortbanking',
        PaymentRequestFactory::REQUEST_TYPE_GIFTCARD => 'GiftCard',
        PaymentRequestFactory::REQUEST_TYPE_CREDITCARD => 'CreditCard',
        PaymentRequestFactory::REQUEST_TYPE_TRANSFER => 'Transfer',
        PaymentRequestFactory::REQUEST_TYPE_AFTERPAY => 'AfterPay',
        PaymentRequestFactory::REQUEST_TYPE_KLARNA => 'Klarna',
        PaymentRequestFactory::REQUEST_TYPE_APPLEPAY => 'ApplePay',
        PaymentRequestFactory::REQUEST_TYPE_BELFIUS => 'Belfius',
        PaymentRequestFactory::REQUEST_TYPE_IDIN => 'Idin',
    );

    final public static function create($request_type_id, $data = array())
    {

        $class_name = self::$valid_request_types[$request_type_id];
        autoload($class_name); //Try to find class in api directory
        if (!class_exists($class_name)) {
            throw new Exception('Payment method not found', '1'); //TODO: ExceptionPayment
        }
        return new $class_name($data);
    }
}
