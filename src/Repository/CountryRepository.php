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

use Buckaroo\PrestaShop\Src\Entity\BkCountries;
use Doctrine\ORM\EntityRepository;

class CountryRepository extends EntityRepository
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

    public function getCountriesFromDB(): array
    {
        $countries = $this->findAll();  // Use Doctrine's default findAll method
        $result = [];

        foreach ($countries as $country) {
            $result[] = [
                'id' => $country->getCountryId(),
                'name' => strtolower($country->getName()),
                'iso_code_2' => $country->getIsoCode2(),
                'iso_code_3' => $country->getIsoCode3(),
                'call_prefix' => $country->getCallPrefix(),
                'icon' => \Tools::strtolower($country->getIsoCode2()) . '.jpg',
            ];
        }

        return $result;
    }

    public function checkAndInsertNewCountries()
    {
        $dbCountries = $this->getCountriesFromDB();
        $dbCountryIds = array_column($dbCountries, 'id');

        $langId = \Context::getContext()->language->id;
        $rawCountries = \Country::getCountries($langId, true);
        $processedCountries = $this->processCountries($rawCountries);

        $newCountries = array_filter($processedCountries, function ($country) use ($dbCountryIds) {
            return !in_array($country['id'], $dbCountryIds);
        });

        if ($newCountries) {
            foreach ($newCountries as $countryData) {
                $country = new BkCountries();
                $country->setCountryId($countryData['id']);
                $country->setName($countryData['name']);
                $country->setIsoCode2($countryData['iso_code_2']);
                $country->setIsoCode3($countryData['iso_code_3']);
                $country->setCallPrefix($countryData['call_prefix']);
                $country->setIcon($countryData['icon']);
                $country->setCreatedAt(new \DateTime());
                $country->setUpdatedAt(new \DateTime());
                $this->_em->persist($country);
            }
            $this->_em->flush();
        }

        return array_merge($dbCountries, $newCountries);
    }
}
