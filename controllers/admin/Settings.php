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
    private ?BuckarooSettingsService $settingsService = null;

    /**
     * Get the settings service using proper DI pattern
     * Lazy-loads the service only when needed and caches it
     */
    private function getSettingsService(): BuckarooSettingsService
    {
        if ($this->settingsService === null) {
            try {
                // Use DI to get the service, or fallback to instantiation if not available
                $this->settingsService = $this->has('buckaroo.settings.service') ? 
                    $this->get('buckaroo.settings.service') : new BuckarooSettingsService();
            } catch (\Exception $e) {
                // Container or service not available, fallback to direct instantiation
                $this->settingsService = new BuckarooSettingsService();
            }
        }
        
        return $this->settingsService;
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
