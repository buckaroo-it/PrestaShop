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

class Buckaroo3OrderingsModuleFrontController extends BaseApiController
{
    private $bkOrderingRepository;
    public $module;

    public function __construct()
    {
        parent::__construct();

        $this->bkOrderingRepository = $this->module->getBuckarooOrderingRepository();
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
        $countryCode = Tools::getValue('country');
        $countryCode = !empty($countryCode) ? $countryCode : null;

        $ordering = $this->getOrdering($countryCode);

        $this->sendResponse([
            'status' => true,
            'orderings' => $ordering,
        ]);
    }

    private function getOrdering($countryCode)
    {
        return $this->bkOrderingRepository->getOrdering($countryCode);
    }

    private function handlePost()
    {
        $data = $this->getJsonInput();

        $countryId = $this->getValueOrNull($data, 'country_id');
        $value = $this->getValueOrNull($data, 'value');

        if (!$value) {
            $this->sendResponse([
                'status' => false,
                'message' => 'Missing or invalid data',
            ]);

            return;
        }

        $result = $this->bkOrderingRepository->updateOrdering(json_encode($value), $countryId);
        $this->sendResponse(['status' => $result]);
    }

    private function getValueOrNull(array $data, $key)
    {
        return isset($data[$key]) && !empty($data[$key]) ? $data[$key] : null;
    }
}
