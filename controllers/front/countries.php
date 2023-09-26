<?php

use Buckaroo\Prestashop\Repository\CountryRepository;

include_once dirname(__FILE__) . '/BaseApiController.php';

class Buckaroo3CountriesModuleFrontController extends BaseApiController
{
    public function initContent()
    {
        parent::initContent();
        $this->authenticate();

        $countryRepository = new CountryRepository();
        $countries = $countryRepository->checkAndInsertNewCountries();

        $data = [
            'status' => true,
            'countries' => $countries,
        ];

        $this->sendResponse($data);
    }
}
