<?php
/*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */

namespace Buckaroo\PaymentMethods\Billink\Models;

class Article extends \Buckaroo\Models\Article
{
    protected float $priceExcl;

    /**
     * Ensure article prices are formatted to two decimals for the Billink service payload.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        if (isset($array['price'])) {
            $array['price'] = number_format((float) $array['price'], 2, '.', '');
        }

        if (isset($array['priceExcl'])) {
            $array['priceExcl'] = number_format((float) $array['priceExcl'], 2, '.', '');
        }

        return $array;
    }
}
