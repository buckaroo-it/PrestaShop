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

namespace Buckaroo\PrestaShop\Controllers\admin;

use Buckaroo\PrestaShop\Src\Service\BuckarooCountriesService;

class Countries extends BaseApiController
{
    private BuckarooCountriesService $buckarooCountriesService;

    public function __construct(BuckarooCountriesService $buckarooCountriesService)
    {
        $this->buckarooCountriesService = $buckarooCountriesService;
    }

    public function initContent()
    {
        $countries = $this->buckarooCountriesService->synchronizeCountries();

        $data = [
            'status' => true,
            'countries' => $countries,
        ];

        return $this->sendResponse($data);
    }
}
