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

use Buckaroo\Src\Repository\CountryRepository;

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
