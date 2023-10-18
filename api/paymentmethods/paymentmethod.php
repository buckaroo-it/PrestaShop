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
use Buckaroo\PrestaShop\Src\Repository\RawPaymentMethodRepository;

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

    public function getBuckarooClient()
    {
        return new BuckarooClient(Configuration::get('BUCKAROO_MERCHANT_KEY'), Configuration::get('BUCKAROO_SECRET_KEY'), $this->mode);
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

    public function payGlobal($customPayAction = null)
    {
        (!$customPayAction) ? $payAction = 'pay' : $payAction = $customPayAction;
        $this->payload['currency'] = $this->currency;
        $this->payload['amountDebit'] = $this->amountDebit;
        $this->payload['invoice'] = $this->invoiceId;
        $this->payload['order'] = $this->orderId;
        $this->payload['returnURL'] = $this->returnUrl;
        $this->payload['pushURL'] = $this->pushUrl;
        $this->payload['platformName'] = $this->platformName;
        $this->payload['platformVersion'] = $this->platformVersion;
        $this->payload['moduleVersion'] = $this->moduleVersion;
        $this->payload['moduleSupplier'] = $this->moduleSupplier;
        $this->payload['moduleName'] = $this->moduleName;

        $buckaroo = $this->getBuckarooClient();
        // Pay
        $response = $buckaroo->method($this->type)->$payAction($this->payload);

        return ResponseFactory::getResponse($response);
    }

    public function refundGlobal()
    {// TODO - remove unused code
        $refund_amount = Tools::getValue('refund_amount') ? Tools::getValue('refund_amount') : $this->amountCredit;
        if (in_array($this->type, ['afterpay', 'klarnakp', 'billink'])) {
            $this->data['articles'] = [[
                'refundType' => 'Return',
                'identifier' => 1,
                'description' => 'Refund',
                'quantity' => 1,
                'price' => round($refund_amount, 2),
                'vatPercentage' => 0,
            ]];
        }

        $this->data['currency'] = $this->currency;
        $this->data['amountDebit'] = $this->amountDebit;
        $this->data['amountCredit'] = $refund_amount;
        $this->data['invoice'] = $this->invoiceId;
        $this->data['order'] = $this->orderId;
        $this->data['description'] = $this->description;
        $this->data['originalTransactionKey'] = $this->OriginalTransactionKey;
        $this->data['returnURL'] = $this->returnUrl;
        // $this->data['pushURL']                = $this->pushUrl;
        $this->data['mode'] = $this->mode;
        $buckaroo = $this->getBuckarooClient();
        // Refund
        $response = $buckaroo->method($this->type)->refund($this->data);

        return ResponseFactory::getResponse($response);
    }

    // @codingStandardsIgnoreStart
    public function verify($customVars = [])
    {
        // @codingStandardsIgnoreEnd
        $this->data['services'][$this->type]['action'] = 'verify';
        $this->data['services'][$this->type]['version'] = $this->version;

        $this->payload['returnURL'] = $this->returnUrl;
        $this->payload['pushURL'] = $this->pushUrl;
        $this->payload['platformName'] = $this->platformName;
        $this->payload['platformVersion'] = $this->platformVersion;
        $this->payload['moduleVersion'] = $this->moduleVersion;
        $this->payload['moduleSupplier'] = $this->moduleSupplier;
        $this->payload['moduleName'] = $this->moduleName;
        $this->payload['mode'] = $this->mode;
        $this->payload['additionalParameters']['cid'] = $this->payload['additionalParameters']['cid'];

        $buckaroo = $this->getBuckarooClient();
        // Verify
        $response = $buckaroo->method('idin')->verify($this->payload);

        return ResponseFactory::getResponse($response);
    }

    public function getMode($key)
    {
        $paymentMethodRepository = new RawPaymentMethodRepository();
        $getPaymentMethodMode = $paymentMethodRepository->getPaymentMethodMode($key);

        if (Configuration::get('BUCKAROO_TEST') == 1 && $getPaymentMethodMode == 'live') {
            return 'live';
        }

        return 'test';
    }
}
