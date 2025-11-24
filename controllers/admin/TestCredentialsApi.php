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

use Buckaroo\PrestaShop\Src\Service\BuckarooClientFactory;

if (!defined('_PS_VERSION_')) {
    exit;
}

class TestCredentialsApi extends BaseApiController
{
    private BuckarooClientFactory $clientFactory;

    public function __construct(BuckarooClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    public function initContent()
    {
        $data = $this->getJsonInput();

        if (empty($data['website_key']) || empty($data['secret_key'])) {
            return $this->sendResponse([
                'status' => false,
                'message' => 'Missing website_key or secret_key',
            ]);
        }

        $buckarooClient = $this->clientFactory->create($data['website_key'], $data['secret_key']);
        $status = $buckarooClient->confirmCredential();

        return $this->sendResponse(['status' => $status]);
    }
}
