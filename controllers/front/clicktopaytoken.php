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

use Buckaroo\PrestaShop\Src\Container\ContainerAwareTrait;
use Buckaroo\PrestaShop\Src\Service\BuckarooClickToPayService;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Proxies the Click to Pay OAuth token request so the merchant's Token API
 * client secret never reaches the browser (and to avoid a cross-origin call
 * from the checkout page).
 */
class Buckaroo3ClickToPayTokenModuleFrontController extends ModuleFrontController
{
    use ContainerAwareTrait;

    public $ssl = true;
    public $ajax = true;
    public $content_only = true;

    public function initContent()
    {
        // This endpoint spends the merchant's Click to Pay credentials, so it
        // must only serve a genuine checkout request: an active cart belonging
        // to the current customer with Click to Pay enabled.
        if (!$this->isTrustedCheckoutRequest()) {
            $this->respond(['error' => 'Unauthorized request'], 403);

            return;
        }

        $clickToPayService = $this->resolveClickToPayService();

        if (!$clickToPayService->isConfigured()) {
            $this->respond(['error' => 'Click to Pay credentials are not configured.'], 500);

            return;
        }

        $token = $clickToPayService->getAccessToken();
        if ($token === null) {
            $this->respond(['error' => 'Failed to obtain access token.'], 500);

            return;
        }

        $this->respond($token);
    }

    private function isTrustedCheckoutRequest(): bool
    {
        if (Tools::getValue('token') !== Tools::getToken(false)) {
            return false;
        }

        $cart = $this->context->cart;
        if (!$cart || !Validate::isLoadedObject($cart) || (int) $cart->nbProducts() <= 0) {
            return false;
        }

        if ((int) $cart->id_customer <= 0
            || (int) $cart->id_customer !== (int) $this->context->customer->id
        ) {
            return false;
        }

        return (bool) $this->module->isPaymentModeActive(BuckarooClickToPayService::METHOD_NAME);
    }

    private function resolveClickToPayService(): BuckarooClickToPayService
    {
        if ($this->hasService('buckaroo.config.api.clicktopay.service')) {
            return $this->getService('buckaroo.config.api.clicktopay.service');
        }

        return new BuckarooClickToPayService($this->module->getBuckarooConfigService());
    }

    private function respond(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json;charset=utf-8');
        header('Cache-Control: no-store');

        $this->ajaxRender(json_encode($payload));
    }
}
