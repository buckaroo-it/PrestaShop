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

namespace Buckaroo\PrestaShop\Src\Refund\Request;

use Buckaroo\PrestaShop\Src\Repository\RawGiftCardsRepository;

class PaymentMethodHelper
{
    /**
     * Check if the payment method is a type of credit card.
     *
     * @param string $method The payment method to check.
     * @return bool Returns true if the method is a type of credit card, false otherwise.
     */
    public static function isCreditCardMethod(string $method): bool {
        $creditCardMethods = [
            'creditcard', 'mastercard', 'visa',
            'amex', 'vpay', 'maestro',
            'visaelectron', 'cartebleuevisa', 'cartebleue',
            'cartebancaire', 'dankort', 'nexi',
            'postepay',
        ];

        return in_array($method, $creditCardMethods);
    }

    /**
     * Check if the payment method is a gift card service code.
     *
     * @param string $method The payment method to check.
     * @return bool Returns true if the method is a gift card service code, false otherwise.
     */
    public static function isGiftCardMethod(string $method): bool {
        // First check if it's the generic giftcard method
        if ($method === 'giftcard') {
            return true;
        }

        // Check if it's a specific gift card service code
        try {
            $giftCardRepository = new RawGiftCardsRepository();
            $giftCards = $giftCardRepository->getGiftCardsFromDB();
            
            foreach ($giftCards as $giftCard) {
                if (isset($giftCard['code']) && $giftCard['code'] === $method) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }
}