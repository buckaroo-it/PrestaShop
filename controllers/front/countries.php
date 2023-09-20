<?php
include_once dirname(__FILE__) . '/BaseApiController.php';

class Buckaroo3CountriesModuleFrontController extends BaseApiController
{
    public function initContent()
    {
        parent::initContent();
        $this->authenticate();

        $context = Context::getContext();
        $langId = $context->language->id;

        $rawCountries = Country::getCountries($langId, true);

        $processedCountries = $this->processCountries($rawCountries);

        $data = [
            "status" => true,
            "countries" => $processedCountries
        ];

        $this->sendResponse($data);
    }

    private function processCountries($countries)
    {
        $result = [];

        foreach ($countries as $country) {
            $result[] = [
                "id" => $country['id_country'],
                "name" => strtolower($country['name']),
                "iso_code_2" => $country['iso_code'],
                "iso_code_3" => Country::getIsoById($country['id_country']),
                "call_prefix" => $country['call_prefix'],
                "icon" => Tools::strtolower($country['iso_code']) . '.jpg'
            ];
        }

        return $result;
    }
}
