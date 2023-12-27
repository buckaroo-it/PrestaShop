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
require_once dirname(__FILE__) . '/../../library/logger.php';
require_once dirname(__FILE__) . '/../abstract.php';
require_once dirname(__FILE__) . '/responsefactory.php';
require_once _PS_ROOT_DIR_ . '/modules/buckaroo3/vendor/autoload.php';
use Buckaroo\BuckarooClient;
use Buckaroo\PrestaShop\Classes\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class PaymentMethod extends BuckarooAbstract
{
    protected $type;
    public $currency;
    public $amountDebit;
    public $amountCredit = 0;
    public $orderId;
    public $invoiceId;
    public $description;
    public $OriginalTransactionKey;
    public $returnUrl;
    public $pushUrl;
    public $platformName;
    public $platformVersion;
    public $moduleVersion;
    public $moduleSupplier;
    public $moduleName;
    public $mode;
    public $version;
    public $usecreditmanagment = 0;
    protected $data = [];
    protected $payload = [];

    public function getBuckarooClient($mode)
    {
        return new BuckarooClient(Configuration::get('BUCKAROO_MERCHANT_KEY'), Configuration::get('BUCKAROO_SECRET_KEY'), $mode);
    }

    public function executeCustomPayAction($action)
    {
        return $this->payGlobal($action);
    }

    // @codingStandardsIgnoreStart
    public function pay($customVars = [])
    {
        // @codingStandardsIgnoreEnd
        $this->data['services'][$this->type]['action'] = 'Pay';
        $this->data['services'][$this->type]['version'] = $this->version;

        return $this->payGlobal();
    }

    public function refund()
    {
        $this->data['services'][$this->type]['action'] = 'Refund';
        $this->data['services'][$this->type]['version'] = $this->version;

        return $this->refundGlobal();
    }

    /**
     * @throws Exception
     */
    public function payGlobal($customPayAction = null)
    {
        (!$customPayAction) ? $payAction = 'pay' : $payAction = $customPayAction;

        $this->payload = array_merge([
            'currency' => $this->currency,
            'amountDebit' => $this->amountDebit,
            'invoice' => $this->invoiceId,
            'description' => $this->description,
            'order' => $this->orderId,
            'returnURL' => $this->returnUrl,
            'pushURL' => $this->pushUrl,
            'platformName' => $this->platformName,
            'platformVersion' => $this->platformVersion,
            'moduleVersion' => $this->moduleVersion,
            'moduleSupplier' => $this->moduleSupplier,
            'moduleName' => $this->moduleName,
        ], $this->payload);

        $buckaroo = $this->getBuckarooClient(Config::getMode($this->type));
        // Pay
        $response = $buckaroo->method($this->type)->$payAction($this->payload);

        return ResponseFactory::getResponse($response);
    }

    /**
     * @throws Exception
     */
    public function refundGlobal()
    {
        $refund_amount = Tools::getValue('refund_amount') ? Tools::getValue('refund_amount') : $this->amountCredit;
        if (in_array($this->type, ['afterpay', 'billink'])) {
            $this->data['articles'] = [[
                'refundType' => 'Return',
                'identifier' => 1,
                'description' => 'Refund',
                'quantity' => 1,
                'price' => round($refund_amount, 2),
                'vatPercentage' => 0,
            ]];
        }

        $this->data = array_merge($this->data,
            [
                'currency' => $this->currency,
                'amountDebit' => $this->amountDebit,
                'amountCredit' => $this->amountCredit,
                'invoice' => $this->invoiceId,
                'order' => $this->orderId,
                'description' => $this->description,
                'originalTransactionKey' => $this->OriginalTransactionKey,
            ]);

        $buckaroo = $this->getBuckarooClient(Config::getMode($this->type));
        // Refund
        $response = $buckaroo->method($this->type)->refund($this->data);

        return ResponseFactory::getResponse($response);
    }

    // @codingStandardsIgnoreStart

    /**
     * @throws Exception
     */
    public function verify($customVars = [])
    {
        // @codingStandardsIgnoreEnd
        $this->data['services'][$this->type]['action'] = 'verify';
        $this->data['services'][$this->type]['version'] = $this->version;

        $this->payload = array_merge($this->payload,
            [
                'returnURL' => $this->returnUrl,
                'pushURL' => $this->pushUrl,
                'platformName' => $this->platformName,
                'platformVersion' => $this->platformVersion,
                'moduleVersion' => $this->moduleVersion,
                'moduleSupplier' => $this->moduleSupplier,
                'moduleName' => $this->moduleName,
            ]);

        $buckaroo = $this->getBuckarooClient(Config::getMode($this->type));
        // Verify
        $response = $buckaroo->method('idin')->verify($this->payload);

        return ResponseFactory::getResponse($response);
    }

    public function setDescription($cartId)
    {
        $description = (string) Configuration::get('BUCKAROO_TRANSACTION_LABEL');
        preg_match_all('/{\w+}/', $description, $matches);

        if (!empty($matches[0])) {
            $order = \Order::getByCartId($cartId);
            $patterns = ['/{order_number}/', '/{shop_name}/'];
            $replacement = [$order->reference, \Context::getContext()->shop->name];

            foreach ($matches[0] as $match) {
                if (!in_array("/$match/", $patterns)) {
                    $property = trim($match, '{}');
                    if (isset($order->$property)) {
                        $replacement[] = $order->$property;
                        $patterns[] = "/$match/";
                    }
                }
            }
            $patterns[] = '/{\w+}/';
            $description = preg_replace($patterns, $replacement, $description);
        }

        $this->description = $description;
    }
}
