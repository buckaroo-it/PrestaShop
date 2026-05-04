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
     * Normalize payment method value for comparisons.
     *
     * @param string $method
     * @return string
     */
    public static function normalizeMethod(string $method): string
    {
        return strtolower(trim($method));
    }

    /**
     * Check if the payment method is a type of credit card.
     *
     * @param string $method The payment method to check.
     * @return bool Returns true if the method is a type of credit card, false otherwise.
     */
    public static function isCreditCardMethod(string $method): bool {
        $method = self::normalizeMethod($method);

        $creditCardMethods = [
            'creditcard', 'mastercard', 'visa',
            'amex', 'vpay', 'maestro',
            'visaelectron', 'cartebleuevisa', 'cartebleue',
            'cartebancaire', 'dankort', 'nexi',
            'postepay',
        ];

        return in_array($method, $creditCardMethods, true);
    }

    /**
     * Check if the payment method is a gift card service code.
     *
     * @param string $method The payment method to check.
     * @return bool Returns true if the method is a gift card service code, false otherwise.
     */
    public static function isGiftCardMethod(string $method): bool {
        $method = self::normalizeMethod($method);

        // First check if it's the generic giftcard method
        if ($method === 'giftcard') {
            return true;
        }

        return in_array($method, self::getGiftCardCodes(), true);
    }

    /**
     * Return known gift card service codes.
     *
     * Prefer DB values, but fall back to static list when table is unavailable
     * to keep refund routing stable.
     *
     * @return array
     */
    private static function getGiftCardCodes(): array
    {
        $giftCardRepository = new RawGiftCardsRepository();
        $giftCards = [];

        try {
            $giftCards = $giftCardRepository->getGiftCardsFromDB();
        } catch (\Exception $e) {
            $giftCards = [];
        }

        if (!is_array($giftCards) || empty($giftCards)) {
            $giftCards = $giftCardRepository->getGiftCardsData();
        }

        $codes = [];
        foreach ($giftCards as $giftCard) {
            if (!isset($giftCard['code']) || !is_scalar($giftCard['code'])) {
                continue;
            }

            $code = self::normalizeMethod((string) $giftCard['code']);
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }
}