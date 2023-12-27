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

use Buckaroo\PrestaShop\Src\Service\BuckarooSettingsService;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Settings extends BaseApiController
{
    private BuckarooSettingsService $settingsService;

    public function __construct()
    {
        parent::__construct();
        $this->settingsService = new BuckarooSettingsService();
    }

    public function initContent()
    {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                return $this->handleGet();
            case 'POST':
                return $this->handlePost();
        }
    }

    private function handleGet()
    {
        $data = [
            'status' => true,
            'settings' => $this->settingsService->getSettings(),
        ];

        return $this->sendResponse($data);
    }

    private function handlePost()
    {
        $data = $this->getJsonInput();

        if ($this->settingsService->isValidData($data)) {
            $this->settingsService->updateSettings($data);

            $data = [
                'status' => true,
                'settings' => $this->settingsService->getSettings(),
            ];

            return $this->sendResponse($data);
        }

        return $this->sendErrorResponse('Invalid input data', 400);
    }
}
