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

abstract class ConfigCore
{
    const WSDL_URL = 'https://checkout.buckaroo.nl/soap/soap.svc?wsdl';
    const WSDL_FILE = '/wsdl/Buckaroo.wsdl';
    const CHANNEL = 'Web';
    const LOCATION = 'https://checkout.buckaroo.nl/soap/';
    const LOCATION_TEST = 'https://testcheckout.buckaroo.nl/soap/';
    const LOG = false;
    const LOG_DIR = '/log/';
    const CERTIFICATE_PATH = 'certificate/';

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
            case 'BUCKAROO_CERTIFICATE_THUMBPRINT':
                $value = '';
                break;
            case 'BUCKAROO_CERTIFICATE_PATH':
                $value = "";
                break;
            case 'CULTURE':
                $value = 'en-US';
                break;
            case 'BUCKAROO_CREDITCARD_CARDS':
                $value = 'amex,mastercard,visa,vpay';
                break;
            case 'BUCKAROO_CREDITCARD_ALLOWED_CARDS':
                $value = 'amex,mastercard,visa,vpay';
                break;
            case 'BUCKAROO_GIFTCARDS_CARDS':
                $value = 'ideal,ippies,babygiftcard,babyparkgiftcard,beautywellness,boekenbon,boekenvoordeel,designshopsgiftcard,fashioncheque,fashionucadeaukaart,fijncadeau,koffiecadeau,kokenzo,kookcadeau,nationaleentertainmentcard,naturesgift,podiumcadeaukaart,shoesaccessories,webshopgiftcard,wijncadeau,wonenzo,yourgift,vvvgiftcard';
                break;
            case 'BUCKAROO_GIFTCARD_ALLOWED_CARDS':
                $value = 'ideal,ippies,babygiftcard,babyparkgiftcard,beautywellness,boekenbon,boekenvoordeel,designshopsgiftcard,fashioncheque,fashionucadeaukaart,fijncadeau,koffiecadeau,kokenzo,kookcadeau,nationaleentertainmentcard,naturesgift,podiumcadeaukaart,shoesaccessories,webshopgiftcard,wijncadeau,wonenzo,yourgift,vvvgiftcard';
                break;
        }
        return $value;
    }

    public static function getSoftware()
    {
        $Software = new Software();
        $Software->PlatformName = 'API';
        $Software->PlatformVersion = '1';
        $Software->ModuleSupplier = 'Buckaroo';
        $Software->ModuleName = 'Plugin';
        $Software->ModuleVersion = '0.8';
        return $Software;
    }
}
