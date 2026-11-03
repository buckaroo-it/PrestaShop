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

use Buckaroo\PrestaShop\Src\ApplePay\ExpressCheckoutService;
use Buckaroo\PrestaShop\Src\ApplePay\ExpressException;
use Buckaroo\PrestaShop\Src\ApplePay\ExpressSettings;
use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentService;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * JSON endpoints behind the Apple Pay express button: start an express cart,
 * recalculate it for the contact and shipping method chosen in the Apple Pay
 * sheet, and prepare it for the payment once the shopper authorized it.
 */
class Buckaroo3ApplepayexpressModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;
    public $content_only = true;

    /** @var ExpressCheckoutService|null */
    private $expressService;

    public function init()
    {
        parent::init();

        // PrestaShop derives the AJAX flag from the request; these endpoints
        // always answer JSON and must never render a page template.
        $this->ajax = true;
    }

    public function initContent()
    {
        try {
            $this->handle();
        } catch (ExpressException $exception) {
            $this->respond([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'field' => $exception->getContactField(),
            ]);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('Buckaroo Apple Pay express: ' . $exception->getMessage(), 3);
            $this->respond([
                'status' => 'error',
                'message' => $this->module->l('Apple Pay is not available at the moment. Please try again or choose another payment method.', 'applepayexpress'),
                'field' => '',
            ]);
        }
    }

    /**
     * @throws ExpressException
     */
    private function handle(): void
    {
        if (!$this->isPost() || Tools::getValue('token') !== Tools::getToken(false)) {
            $this->respond(['status' => 'error', 'message' => 'Unauthorized request', 'field' => ''], 403);

            return;
        }

        $module = $this->getBuckarooModule();
        $settings = $module->getApplePayExpressSettings();
        if (!$module->active || !$module->isPaymentModeActive(ExpressSettings::METHOD_NAME) || !$settings->isConfigured()) {
            throw new ExpressException($this->module->l('Apple Pay is not available at the moment. Please try again or choose another payment method.', 'applepayexpress'));
        }

        $action = (string) Tools::getValue('action');
        if ($action === 'start') {
            $this->start($settings);

            return;
        }

        $cart = $this->getExpressService()->getExpressCart();
        if ($cart === null) {
            throw new ExpressException($this->module->l('Your Apple Pay session expired. Please try again.', 'applepayexpress'));
        }

        switch ($action) {
            case 'contact':
                $this->respondSummary($this->getExpressService()->estimate($cart, $this->getJsonValue('contact'), null));

                return;
            case 'shipping':
                $this->respondSummary($this->getExpressService()->changeShipping($cart, (string) Tools::getValue('identifier')));

                return;
            case 'authorize':
                $this->authorize($cart);

                return;
            default:
                $this->respond(['status' => 'error', 'message' => 'Unknown action', 'field' => ''], 400);
        }
    }

    /**
     * @throws ExpressException
     */
    private function start(ExpressSettings $settings): void
    {
        $source = (string) Tools::getValue('source');
        if (!in_array($source, ExpressSettings::PLACEMENTS, true) || !$settings->showsOn($source)) {
            throw new ExpressException($this->module->l('Apple Pay is not available at the moment. Please try again or choose another payment method.', 'applepayexpress'));
        }

        $service = $this->getExpressService();
        if ($source === ExpressSettings::PLACEMENT_PRODUCT) {
            $groups = Tools::getValue('group');
            $cart = $service->startFromProduct(
                (int) Tools::getValue('id_product'),
                (int) Tools::getValue('id_product_attribute'),
                is_array($groups) ? $groups : [],
                (int) Tools::getValue('qty', 1)
            );
        } else {
            $cart = $service->startFromCart();
        }

        if (!$service->isAvailable($cart)) {
            throw new ExpressException($this->module->l('Apple Pay is not available for this order. Please choose another payment method.', 'applepayexpress'));
        }

        // From here on the express cart exists: an address the shop cannot
        // deliver to is reported in the sheet, where the shopper can pick another.
        try {
            $summary = $service->estimate($cart, $this->getJsonValue('contact'), null);
        } catch (ExpressException $exception) {
            $this->respond([
                'status' => 'error',
                'started' => true,
                'message' => $exception->getMessage(),
                'field' => $exception->getContactField(),
            ]);

            return;
        }

        $this->respondSummary($summary);
    }

    /**
     * @throws ExpressException
     */
    private function authorize(Cart $cart): void
    {
        $payment = $this->getJsonValue('payment');
        $amount = Tools::getValue('amount');

        if (!is_numeric($amount) || (float) $amount <= 0) {
            throw new ExpressException($this->module->l('The order total has changed. Please try again.', 'applepayexpress'));
        }

        $this->getExpressService()->authorize($cart, $payment, (string) Tools::getValue('identifier'), (float) $amount);

        $this->respond([
            'status' => 'success',
            'action' => $this->context->link->getModuleLink($this->module->name, 'request', [], true),
            'fields' => [
                'method' => ExpressSettings::METHOD_NAME,
                'buckaroo_express' => ExpressSettings::METHOD_NAME,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonValue(string $name): array
    {
        $value = Tools::getValue($name);
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function getExpressService(): ExpressCheckoutService
    {
        if ($this->expressService === null) {
            // The payment service is defined for the shop front (config/front/services.yml),
            // which the module container resolves.
            $module = $this->getBuckarooModule();
            $paymentService = $module->get('buckaroo.config.api.payment.service');
            if (!$paymentService instanceof BuckarooPaymentService) {
                throw new RuntimeException('The Buckaroo payment service is not available.');
            }

            $this->expressService = new ExpressCheckoutService($module, $this->context, $paymentService);
        }

        return $this->expressService;
    }

    private function getBuckarooModule(): Buckaroo3
    {
        if (!$this->module instanceof Buckaroo3) {
            throw new RuntimeException('The Buckaroo module is not loaded.');
        }

        return $this->module;
    }

    private function isPost(): bool
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function respondSummary(array $summary): void
    {
        $this->respond(['status' => 'success'] + $summary);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function respond(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json;charset=utf-8');
        header('Cache-Control: no-store');

        $this->ajaxRender(json_encode($payload));
    }
}
