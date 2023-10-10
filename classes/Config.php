<?php

namespace Buckaroo\PrestaShop\Classes;

use Buckaroo\PrestaShop\Src\Repository\RawPaymentMethodRepository;

class Config
{
    public static function getMode($key)
    {
        $paymentMethodRepository = new RawPaymentMethodRepository();
        $getPaymentMethodMode = $paymentMethodRepository->getPaymentMethodMode($key);
        if (\Configuration::get('BUCKAROO_TEST') == 0 && $getPaymentMethodMode == 'live') {
            return 'live';
        }

        return 'test';
    }
}
