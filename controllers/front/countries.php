<?php
include_once dirname(__FILE__) . '/BaseApiController.php';

class Buckaroo3CountriesModuleFrontController extends BaseApiController
{
    public function initContent()
    {
        parent::initContent();

        $rawCountries = Country::getCountries(Context::getContext()->language->id);


        $countries = [];
        foreach ($rawCountries as $country) {
            $countries[] = [
                "id" => $country['id_country'],
                "name" => strtolower($country['name']),
                "iso_code_2" => $country['iso_code'],
                "iso_code_3" => Country::getIsoById($country['id_country']),
                "call_prefix" => $country['call_prefix'],
                "icon" => Tools::strtolower($country['iso_code']) . '.jpg'
            ];
        }

        $data = [
            "status" => true,
            "countries" => $countries
        ];


        $context = Context::getContext();
        $langId = $context->language->id;
        $countries = Country::getCountries($langId, true);
        $countriesWithNames = [];
        foreach ($countries as $key => $country) {
            $countriesWithNames[] = [
                "id" => $country['id_country'],
                "name" => strtolower($country['name']),
                "iso_code_2" => $country['iso_code'],
                "iso_code_3" => Country::getIsoById($country['id_country']),
                "call_prefix" => $country['call_prefix'],
                "icon" => Tools::strtolower($country['iso_code']) . '.jpg'
            ];
        }
        $data = [
            "status" => true,
            "countries" => $countriesWithNames
        ];

        $this->sendResponse($data);
    }
}
