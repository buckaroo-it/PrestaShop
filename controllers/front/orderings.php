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
include_once dirname(__FILE__) . '/BaseApiController.php';

use Buckaroo\PrestaShop\Src\Repository\OrderingRepository;

class Buckaroo3OrderingsModuleFrontController extends BaseApiController
{
    private $orderingRepository;

    public function __construct()
    {
        parent::__construct();

        $this->orderingRepository = new OrderingRepository();  // Instantiate the repository
    }

    public function initContent()
    {
        parent::initContent();
        $this->authenticate();

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $this->handleGet();
                break;
            case 'POST':
                $this->handlePost();
                break;
        }
    }

    private function handleGet()
    {
        $countryCode = null;
        if(Tools::getValue('country')){
            $countryCode = Tools::getValue('country');  // Get
        }

        $ordering = $this->getOrdering($countryCode);

        $data = [
            'status' => true,
            'orderings' => $ordering,
        ];

        $this->sendResponse($data);
    }

    private function getOrdering($countryCode)
    {
        return $this->orderingRepository->getOrdering($countryCode);
    }

    private function handlePost()
    {
        $data = $this->getJsonInput();

        $countryId = isset($data['country_id']) ? $data['country_id'] : null;
        $value = isset($data['value']) ? json_encode($data['value']) : null;
        $createdAt = isset($data['created_at']) ? $data['created_at'] : null;

        // Check for missing or invalid data
        if ($value === null) {
            $response = [
                'status' => false,
                'message' => 'Missing or invalid data',
            ];
            $this->sendResponse($response);
            return;
        }

        $result = $this->orderingRepository->updateOrdering($value, $countryId);

        // Prepare and send the response
        if ($result) {
            $response = [
                'status' => true
            ];
        } else {
            $response = [
                'status' => false
            ];
        }
        $this->sendResponse($response);
    }
}
