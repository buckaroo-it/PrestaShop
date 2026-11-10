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

namespace Buckaroo\PrestaShop\Src\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Decides which payments create the PrestaShop order only after a successful payment.
 *
 * Only methods the customer completes right away qualify. Methods that need the
 * order first keep the order-before-payment flow: bank transfer, SEPA direct debit
 * and PayPerEmail (paid later), Multibanco and MB WAY (paid later outside the
 * checkout), Riverty, Klarna, in3 and Billink (authorized on the order), giftcards
 * and iDIN.
 */
class OrderAfterPayment
{
    public const METHODS = [
        'ideal',
        'wero',
        'bancontactmrcash',
        'creditcard',
        'paypal',
        'applepay',
        'googlepay',
        'clicktopay',
        'paybybank',
        'eps',
        'przelewy24',
        'trustly',
        'belfius',
        'kbcpaymentbutton',
        'alipay',
        'wechatpay',
        'blik',
        'twint',
        'swish',
        'bizum',
    ];

    /**
     * @param bool $hasGiftcardPayment a giftcard already paid part of the cart; the
     *                                 remainder then keeps the order-first flow
     */
    public static function appliesTo(string $method, bool $hasGiftcardPayment = false): bool
    {
        return !$hasGiftcardPayment
            && in_array(strtolower($method), self::METHODS, true);
    }

    /**
     * Whether the amount Buckaroo collected covers the order total (to the cent).
     */
    public static function amountMatches(float $paid, float $expected): bool
    {
        return abs(round($paid, 2) - round($expected, 2)) < 0.005;
    }
}
