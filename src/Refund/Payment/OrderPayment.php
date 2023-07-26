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

namespace Buckaroo\Prestashop\Refund\Payment;

use OrderPayment as DefaultOrderPayment;

class OrderPayment extends DefaultOrderPayment
{

    public function __construct($id = null, $id_lang = null, $id_shop = null, $translator = null)
    {
        $this->updateValidationAllowNegativeAmount();
        parent::__construct($id, $id_lang, $id_shop, $translator);
    }

    private function updateValidationAllowNegativeAmount()
    {
        if (
            isset(self::$definition['fields']['amount']['validate'])
        ) {
            self::$definition['fields']['amount']['validate'] = 'isAnything';
        }
    }
}
