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
include_once dirname(__FILE__) . '/functions.php';

if (!defined('_PS_VERSION_')) {
    exit;
}
class PaymentRequestFactory
{
    public const REQUEST_TYPE_PAYPAL = 'paypal';
    public const REQUEST_TYPE_IDEAL = 'ideal';
    public const REQUEST_TYPE_PAYBYBANK = 'paybybank';
    public const REQUEST_TYPE_GIROPAY = 'giropay';
    public const REQUEST_TYPE_KBCPAYMENTBUTTON = 'kbcpaymentbutton';
    public const REQUEST_TYPE_SEPADIRECTDEBIT = 'sepadirectdebit';
    public const REQUEST_TYPE_BANCONTACTMRCASH = 'bancontactmrcash';
    public const REQUEST_TYPE_SOFORTBANKING = 'sofortueberweisung';
    public const REQUEST_TYPE_GIFTCARD = 'giftcard';
    public const REQUEST_TYPE_CREDITCARD = 'creditcard';
    public const REQUEST_TYPE_TRANSFER = 'transfer';
    public const REQUEST_TYPE_AFTERPAY = 'afterpay';
    public const REQUEST_TYPE_KLARNA = 'klarna';
    public const REQUEST_TYPE_APPLEPAY = 'applepay';
    public const REQUEST_TYPE_BELFIUS = 'belfius';
    public const REQUEST_TYPE_IDIN = 'idin';
    public const REQUEST_TYPE_IN3 = 'in3';
    public const REQUEST_TYPE_IN3OLD = 'in3Old';
    public const REQUEST_TYPE_BILLINK = 'billink';
    public const REQUEST_TYPE_EPS = 'eps';
    public const REQUEST_TYPE_PAYCONIQ = 'payconiq';
    public const REQUEST_TYPE_PAYPEREMAIL = 'payperemail';
    public const REQUEST_TYPE_PRZELEWY24 = 'przelewy24';
    public const REQUEST_TYPE_TINKA = 'tinka';
    public const REQUEST_TYPE_TRUSTLY = 'trustly';
    public const REQUEST_TYPE_WECHATPAY = 'wechatpay';
    public const REQUEST_TYPE_ALIPAY = 'alipay';
    public const REQUEST_TYPE_MULTIBANCO = 'multibanco';
    public const REQUEST_TYPE_MBWAY = 'mbway';
    public const REQUEST_TYPE_KNAKEN = 'knaken';

    // Request types (Payment Methods).
    public static $valid_request_types = [
        PaymentRequestFactory::REQUEST_TYPE_PAYPAL => 'PayPal',
        PaymentRequestFactory::REQUEST_TYPE_IDEAL => 'IDeal',
        PaymentRequestFactory::REQUEST_TYPE_PAYBYBANK => 'PayByBank',
        PaymentRequestFactory::REQUEST_TYPE_SEPADIRECTDEBIT => 'SepaDirectDebit',
        PaymentRequestFactory::REQUEST_TYPE_GIROPAY => 'Giropay',
        PaymentRequestFactory::REQUEST_TYPE_KBCPAYMENTBUTTON => 'Kbcpaymentbutton',
        PaymentRequestFactory::REQUEST_TYPE_BANCONTACTMRCASH => 'Bancontactmrcash',
        PaymentRequestFactory::REQUEST_TYPE_SOFORTBANKING => 'Sofortbanking',
        PaymentRequestFactory::REQUEST_TYPE_GIFTCARD => 'GiftCard',
        PaymentRequestFactory::REQUEST_TYPE_CREDITCARD => 'CreditCard',
        PaymentRequestFactory::REQUEST_TYPE_TRANSFER => 'Transfer',
        PaymentRequestFactory::REQUEST_TYPE_AFTERPAY => 'AfterPay',
        PaymentRequestFactory::REQUEST_TYPE_KLARNA => 'Klarna',
        PaymentRequestFactory::REQUEST_TYPE_APPLEPAY => 'ApplePay',
        PaymentRequestFactory::REQUEST_TYPE_BELFIUS => 'Belfius',
        PaymentRequestFactory::REQUEST_TYPE_IDIN => 'Idin',
        PaymentRequestFactory::REQUEST_TYPE_IN3 => 'In3',
        PaymentRequestFactory::REQUEST_TYPE_IN3OLD => 'In3Old',
        PaymentRequestFactory::REQUEST_TYPE_BILLINK => 'Billink',
        PaymentRequestFactory::REQUEST_TYPE_EPS => 'Eps',
        PaymentRequestFactory::REQUEST_TYPE_PAYCONIQ => 'Payconiq',
        PaymentRequestFactory::REQUEST_TYPE_PAYPEREMAIL => 'PayPerEmail',
        PaymentRequestFactory::REQUEST_TYPE_PRZELEWY24 => 'Przelewy24',
        PaymentRequestFactory::REQUEST_TYPE_TINKA => 'Tinka',
        PaymentRequestFactory::REQUEST_TYPE_TRUSTLY => 'Trustly',
        PaymentRequestFactory::REQUEST_TYPE_WECHATPAY => 'Wechatpay',
        PaymentRequestFactory::REQUEST_TYPE_ALIPAY => 'Alipay',
        PaymentRequestFactory::REQUEST_TYPE_MULTIBANCO => 'Multibanco',
        PaymentRequestFactory::REQUEST_TYPE_MBWAY => 'Mbway',
        PaymentRequestFactory::REQUEST_TYPE_KNAKEN => 'Knaken',
    ];

    final public static function create($request_type_id, $data = [])
    {
        $class_name = self::$valid_request_types[$request_type_id];
        autoload($class_name); // Try to find class in api directory

        if (!class_exists($class_name)) {
            throw new Exception('Payment method not found', '1'); // TODO: ExceptionPayment
        }

        return new $class_name($data);
    }
}
