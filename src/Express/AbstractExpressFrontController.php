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

namespace Buckaroo\PrestaShop\Src\Express;

use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentService;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * JSON endpoints behind an express button: start an express cart, recalculate
 * it for the address and shipping method chosen in the wallet sheet, and
 * prepare it for the payment once the shopper authorized it.
 */
abstract class AbstractExpressFrontController extends \ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;
    public $content_only = true;

    /** @var ExpressCheckoutService|null */
    private $expressService;

    abstract protected function getWallet(): Wallet;

    abstract protected function getExpressSettings(\Buckaroo3 $module): AbstractExpressSettings;

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
        } catch (\Throwable $exception) {
            \PrestaShopLogger::addLog('Buckaroo ' . $this->getWallet()->getLabel() . ' express: ' . $exception->getMessage(), 3);
            $this->respond([
                'status' => 'error',
                'message' => $this->getUnavailableMessage(),
                'field' => '',
            ]);
        }
    }

    /**
     * @throws ExpressException
     */
    private function handle(): void
    {
        if (!$this->isPost() || \Tools::getValue('token') !== \Tools::getToken(false)) {
            $this->respond(['status' => 'error', 'message' => 'Unauthorized request', 'field' => ''], 403);

            return;
        }

        $module = $this->getBuckarooModule();
        $settings = $this->getExpressSettings($module);
        if (!$module->active || !$module->isPaymentModeActive($this->getWallet()->getMethod()) || !$settings->isConfigured()) {
            throw new ExpressException($this->getUnavailableMessage());
        }

        $action = (string) \Tools::getValue('action');
        if ($action === 'start') {
            $this->start($settings);

            return;
        }

        $service = $this->getExpressService();
        $cart = $service->getExpressCart();
        if ($cart === null) {
            throw new ExpressException($service->getSessionExpiredMessage());
        }

        switch ($action) {
            case 'contact':
                $this->respondSummary($service->estimate($cart, $this->getJsonValue('contact'), null));

                return;
            case 'shipping':
                $this->respondSummary($service->changeShipping($cart, (string) \Tools::getValue('identifier')));

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
    private function start(AbstractExpressSettings $settings): void
    {
        $source = (string) \Tools::getValue('source');
        if (!in_array($source, AbstractExpressSettings::PLACEMENTS, true) || !$settings->showsOn($source)) {
            throw new ExpressException($this->getUnavailableMessage());
        }

        $service = $this->getExpressService();
        if ($source === AbstractExpressSettings::PLACEMENT_PRODUCT) {
            $groups = \Tools::getValue('group');
            $cart = $service->startFromProduct(
                (int) \Tools::getValue('id_product'),
                (int) \Tools::getValue('id_product_attribute'),
                is_array($groups) ? $groups : [],
                (int) \Tools::getValue('qty', 1)
            );
        } else {
            $cart = $service->startFromCart();
        }

        if (!$service->isAvailable($cart)) {
            throw new ExpressException($service->getNotAvailableForOrderMessage());
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
    private function authorize(\Cart $cart): void
    {
        $service = $this->getExpressService();
        $payment = $this->getJsonValue('payment');
        $amount = \Tools::getValue('amount');

        if (!is_numeric($amount) || (float) $amount <= 0) {
            throw new ExpressException($service->getTotalChangedMessage());
        }

        $service->authorize($cart, $payment, (string) \Tools::getValue('identifier'), (float) $amount);

        $method = $this->getWallet()->getMethod();
        $this->respond([
            'status' => 'success',
            'action' => $this->context->link->getModuleLink($this->module->name, 'request', [], true),
            'fields' => [
                'method' => $method,
                'buckaroo_express' => $method,
            ],
        ]);
    }

    private function getUnavailableMessage(): string
    {
        try {
            return $this->getExpressService()->getUnavailableMessage();
        } catch (\Throwable $exception) {
            return $this->getWallet()->getLabel() . ' is not available at the moment.';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonValue(string $name): array
    {
        $value = \Tools::getValue($name);
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
                throw new \RuntimeException('The Buckaroo payment service is not available.');
            }

            $this->expressService = new ExpressCheckoutService($module, $this->context, $paymentService, $this->getWallet());
        }

        return $this->expressService;
    }

    private function getBuckarooModule(): \Buckaroo3
    {
        if (!$this->module instanceof \Buckaroo3) {
            throw new \RuntimeException('The Buckaroo module is not loaded.');
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
