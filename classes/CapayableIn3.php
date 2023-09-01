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
 * @author    Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
require_once _PS_MODULE_DIR_ . 'buckaroo3/config.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/vendor/autoload.php';

class CapayableIn3
{
    public function isV3(): bool
    {
        return Configuration::get('BUCKAROO_IN3_API_VERSION') !== 'V2';
    }
    public function getLogo(): string
    {
        $logo = Configuration::get('BUCKAROO_IN3_PAYMENT_LOGO');

        if ($logo == '0') {
            return 'buckaroo_in3.png?v';
        }

        return 'buckaroo_in3_ideal.svg?v1';
    }
    public function getSelectedIssuerLogo()
    {
        $issuers = $this->getIssuerList();
        $selectedIssuer = array_filter($issuers, function ($issuer) {
            return $issuer['selected'];
        });
        if (count($selectedIssuer) > 0) {
            $selectedIssuer = reset($selectedIssuer);

            return 'paybybank/SVG/' . $selectedIssuer['logo'];
        } else {
            return 'buckaroo_paybybank.gif?v';
        }
    }

    public function getMethod(): string
    {
        if($this->isV3()) {
            return 'in3';
        }
        return 'in3Old';
    }
}
