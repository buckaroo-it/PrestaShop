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

namespace Buckaroo\PrestaShop\Src\Repository;

class CountryRepository
{
    private function processCountries($countries)
    {
        $result = [];

        foreach ($countries as $country) {
            $result[] = [
                'id' => $country['id_country'],
                'name' => strtolower($country['name']),
                'iso_code_2' => $country['iso_code'],
                'iso_code_3' => \Country::getIsoById($country['id_country']),
                'call_prefix' => $country['call_prefix'],
                'icon' => \Tools::strtolower($country['iso_code']) . '.jpg',
            ];
        }

        return $result;
    }

    public function getCountries(): array
    {
        $langId = \Context::getContext()->language->id;
        $rawCountries = \Country::getCountries($langId, true);

        return $this->processCountries($rawCountries);
    }

    public function getCountryByIsoCode2($isoCode2)
    {
        $countries = $this->getCountries();
        $country = array_filter($countries, function ($c) use ($isoCode2) {
            return $c['iso_code_2'] == $isoCode2;
        });

        if (empty($country)) {
            return null;
        }

        return reset($country);
    }
}
