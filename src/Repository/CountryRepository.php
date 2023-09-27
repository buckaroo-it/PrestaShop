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

namespace Buckaroo\Src\Repository;

final class CountryRepository
{
    private $db;
    private $context;

    public function __construct()
    {
        $this->db = \Db::getInstance();
        $this->context = \Context::getContext();
    }

    public function insertCountries()
    {
        $langId = $this->context->language->id;
        $rawCountries = \Country::getCountries($langId, true);
        $processedCountries = $this->processCountries($rawCountries);
        $this->insertCountriesToDB($processedCountries);

        return $processedCountries;
    }

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

    public function getCountriesFromDB()
    {
        $query = 'SELECT * FROM ' . _DB_PREFIX_ . 'bk_countries';

        return $this->db->executeS($query);
    }

    public function checkAndInsertNewCountries()
    {
        $dbCountries = $this->getCountriesFromDB();
        $dbCountryIds = array_column($dbCountries, 'country_id');

        $langId = $this->context->language->id;
        $rawCountries = \Country::getCountries($langId, true);
        $processedCountries = $this->processCountries($rawCountries);

        $newCountries = array_filter($processedCountries, function ($country) use ($dbCountryIds) {
            return !in_array($country['id'], $dbCountryIds);
        });

        if ($newCountries) {
            $this->insertCountriesToDB($newCountries);
        }

        return array_merge($dbCountries, $newCountries);
    }

    private function insertCountriesToDB($countries)
    {
        foreach ($countries as $countryData) {
            $data = $this->prepareData($countryData);
            $result = $this->db->insert('bk_countries', $data);
            if (!$result) {
                throw new \Exception('Database error: Unable to insert country');
            }
        }
    }

    private function prepareData($countryData)
    {
        return [
            'country_id' => pSQL($countryData['id']),
            'name' => pSQL($countryData['name']),
            'iso_code_2' => pSQL($countryData['iso_code_2']),
            'iso_code_3' => pSQL($countryData['iso_code_3']),
            'call_prefix' => pSQL($countryData['call_prefix']),
            'icon' => pSQL($countryData['icon']),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}
