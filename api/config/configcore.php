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
 */// TODO - considering to remove this
abstract class ConfigCore
{
    public const LOG = false;
    public const LOG_DIR = '/log/';

    // @codingStandardsIgnoreStart
    public static function get($key)
    {
        $value = '';
        switch ($key) {
            case 'BUCKAROO_TEST':
                $value = '1';
                break;
            case 'BUCKAROO_MERCHANT_KEY':
                $value = '';
                break;
            case 'BUCKAROO_SECRET_KEY':
                $value = '';
                break;
            case 'CULTURE':
                $value = 'en-US';
                break;
            case 'BUCKAROO_CREDITCARD_CARDS':
                $value = 'amex,mastercard,visa,vpay,postepay';
                break;
            case 'BUCKAROO_CREDITCARD_ALLOWED_CARDS':
                $value = 'amex,mastercard,visa,vpay,postepay';
                break;
            case 'BUCKAROO_GIFTCARDS_CARDS':
                $value = 'ideal,ippies,babygiftcard,babyparkgiftcard,beautywellness,boekenbon,boekenvoordeel,designshopsgiftcard,fashioncheque,fashionucadeaukaart,fijncadeau,koffiecadeau,kokenzo,kookcadeau,nationaleentertainmentcard,naturesgift,podiumcadeaukaart,shoesaccessories,webshopgiftcard,wijncadeau,wonenzo,yourgift,vvvgiftcard,customgiftcard,customgiftcard2,customgiftcard3';
                break;
            case 'BUCKAROO_GIFTCARD_ALLOWED_CARDS':
                $value = 'ideal,ippies,babygiftcard,babyparkgiftcard,beautywellness,boekenbon,boekenvoordeel,designshopsgiftcard,fashioncheque,fashionucadeaukaart,fijncadeau,koffiecadeau,kokenzo,kookcadeau,nationaleentertainmentcard,naturesgift,podiumcadeaukaart,shoesaccessories,webshopgiftcard,wijncadeau,wonenzo,yourgift,vvvgiftcard,customgiftcard,customgiftcard2,customgiftcard3';
                break;
        }

        return $value;
    }
}
