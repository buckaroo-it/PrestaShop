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

namespace Buckaroo\PrestaShop\Src;


class AddressComponents
{
    /**
     * Split address to parts
     *
     * @param string $address
     *
     * @return array
     */
    public static function getAddressComponents($address)
    {
        $result = [];
        $result['house_number'] = '';
        $result['number_addition'] = '';

        $address = str_replace(['?', '*', '[', ']', ',', '!'], ' ', $address);
        $address = preg_replace('/\s\s+/', ' ', $address);

        preg_match('/^([0-9]*)(.*?)([0-9]+)(.*)/', $address, $matches);

        if (!empty($matches[2])) {
            $result['street'] = trim($matches[1] . $matches[2]);
            $result['house_number'] = trim($matches[3]);
            $result['number_addition'] = trim($matches[4]);
        } else {
            $result['street'] = $address;
        }

        return $result;
    }
}