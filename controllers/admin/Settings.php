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
    private function getSettingsService(): BuckarooSettingsService
    {
        try {
            if ($this->has('buckaroo.settings.service')) {
                return $this->get('buckaroo.settings.service');
            }
        } catch (\Exception $e) {
            // Container not available
        }
        
        return new BuckarooSettingsService();
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
        $settingsService = $this->getSettingsService();
        $data = [
            'status' => true,
            'settings' => $settingsService->getSettings(),
        ];

        return $this->sendResponse($data);
    }

    private function handlePost()
    {
        $settingsService = $this->getSettingsService();
        $data = $this->getJsonInput();

        if ($settingsService->isValidData($data)) {
            $settingsService->updateSettings($data);

            $data = [
                'status' => true,
                'settings' => $settingsService->getSettings(),
            ];

            return $this->sendResponse($data);
        }

        return $this->sendErrorResponse('Invalid input data', 400);
    }
}
