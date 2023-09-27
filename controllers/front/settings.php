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

if (!defined('_PS_VERSION_')) {
    return;
}
include dirname(__FILE__) . '/BaseApiController.php';
use Buckaroo\Src\Service\BuckarooSettingsService;

class Buckaroo3SettingsModuleFrontController extends BaseApiController
{
    private $settingsService;

    public function __construct()
    {
        parent::__construct();
        $this->settingsService = new BuckarooSettingsService();
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
        $data = [
            'status' => true,
            'settings' => $this->settingsService->getSettings(),
        ];

        $this->sendResponse($data);
    }

    private function getReturnUrl()
    {
        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http');

        return $protocol . '://' . $_SERVER['SERVER_NAME'] . __PS_BASE_URI__ . 'index.php?fc=module&module=buckaroo3&controller=userreturn';
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

            $this->sendResponse($data);
        } else {
            $this->sendErrorResponse('Invalid input data', 400);
        }
    }
}
