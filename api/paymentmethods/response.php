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
require_once _PS_ROOT_DIR_ . '/modules/buckaroo3/vendor/autoload.php';
use Buckaroo\BuckarooClient;
use Buckaroo\Handlers\Reply\ReplyHandler;
use Buckaroo\Transaction\Response\TransactionResponse;

abstract class Response extends BuckarooAbstract
{
    // false if not received response
    private $received = false;
    // true if validated and securety checked
    private $validated = false;
    // request is test?
    private $test = true;
    private $signature;
    private $isPush;
    // payment key
    public $payment;
    public $payment_method;
    public $statuscode;
    public $statuscode_detail;
    public $status;
    public $statusmessage;
    public $message;
    public $invoice;
    public $invoicenumber;
    public $amount_credit;
    public $amount;
    public $currency;
    public $timestamp;
    public $ChannelError;
    public $brq_transaction_type;
    public $brq_relatedtransaction_partialpayment;
    public $brq_relatedtransaction_refund;
    // transaction key
    public $transactions;
    // if is errors, othervise = null
    public $parameterError;

    protected ?TransactionResponse $response = null;

    public function __construct(TransactionResponse $response = null)
    {
        if ($response) {
            $this->response = $response;
        } else {
            $this->isPush = $this->isPushRequest();
            $this->received = true;
            $this->parsePushRequest();
        }
    }

    /**
     * Get code required for payment
     *
     * @param string $configCode
     *
     * @return string
     */
    protected function getPaymentCode(string $configCode): string
    {
        if ($configCode === 'Capayable') {
            return 'in3';
        }

        return $configCode;
    }

    private function parsePushRequest()
    {
        if (!$this->isPushRequest()) {
            return false;
        }

        $this->payment = $this->setPostVariable('brq_payment');
        if (Tools::getValue('brq_payment_method')) {
            $this->payment_method = $this->getPaymentCode(Tools::getValue('brq_payment_method'));
        } elseif (Tools::getValue('brq_transaction_method')) {
            $this->payment_method = $this->getPaymentCode(Tools::getValue('brq_transaction_method'));
        }

        $this->statuscode = $this->setPostVariable('brq_statuscode');
        $this->statusmessage = $this->setPostVariable('brq_statusmessage');
        $this->statuscode_detail = $this->setPostVariable('brq_statuscode_detail');
        $this->brq_relatedtransaction_partialpayment = $this->setPostVariable('brq_relatedtransaction_partialpayment');
        $this->brq_transaction_type = $this->setPostVariable('brq_transaction_type');
        $this->brq_relatedtransaction_refund = $this->setPostVariable('brq_relatedtransaction_refund');
        $this->invoice = $this->setPostVariable('brq_invoicenumber');
        $this->invoicenumber = $this->setPostVariable('brq_invoicenumber');
        $this->amount = $this->setPostVariable('brq_amount');
        if (Tools::getValue('brq_amount_credit')) {
            $this->amount_credit = Tools::getValue('brq_amount_credit');
        }

        $this->currency = $this->setPostVariable('brq_currency');
        $this->test = $this->setPostVariable('brq_test');
        $this->timestamp = $this->setPostVariable('brq_timestamp');
        $this->transactions = $this->setPostVariable('brq_transactions');
        $this->signature = $this->setPostVariable('brq_signature');

        if (!empty($this->statuscode)) {
            $responseArray = $this->responseCodes[(int) $this->statuscode];
            $this->status = $responseArray['status'];
            $this->message = $responseArray['message'];
        }
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->response->isSuccess();
    }

    /**
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->response->isFailed();
    }

    /**
     * @return bool
     */
    public function isCanceled(): bool
    {
        return $this->response->isCanceled();
    }

    /**
     * @return bool
     */
    public function isAwaitingConsumer(): bool
    {
        return $this->response->isAwaitingConsumer();
    }

    /**
     * @return bool
     */
    public function isPendingProcessing(): bool
    {
        return $this->response->isPendingProcessing();
    }

    /**
     * @return bool
     */
    public function isWaitingOnUserInput(): bool
    {
        return $this->response->isWaitingOnUserInput();
    }

    /**
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->response->isRejected();
    }

    // Determine if is buckaroo response or push
    private function isPushRequest()
    {
        if (Tools::getValue('brq_statuscode')) {
            return true;
        }

        return false;
    }

    public function getServiceParameters()
    {
        return $this->response->getServiceParameters();
    }

    public function hasSomeError()
    {
        return $this->response->hasSomeError();
    }

    public function getSomeError()
    {
        return $this->response->getSomeError();
    }

    public function isTest()
    {
        return $this->response->get('IsTest') === true;
    }

    public function isValid()
    {
        if (!$this->validated) {
            if ($this->isPush) {
                $buckaroo = new BuckarooClient(Configuration::get('BUCKAROO_MERCHANT_KEY'), Configuration::get('BUCKAROO_SECRET_KEY'));
                try {
                    $reply_handler = new ReplyHandler($buckaroo->client()->config(), $_POST);
                    $reply_handler->validate();

                    return $this->validated = $reply_handler->isValid();
                } catch (Exception $e) {
                }
            } elseif ($this->response) {
                $this->validated = (!$this->response->isValidationFailure());
            }
        }

        return $this->validated;
    }

    public function hasSucceeded()
    {
        if (isset($this->response)) {
            try {
                if ($this->isValid()) {
                    if ($this->isPendingProcessing() || $this->isAwaitingConsumer() || $this->isWaitingOnUserInput() || $this->isSuccess()) {
                        return true;
                    }
                }
            } catch (Exception $e) {
            }
        } elseif (in_array($this->status, [self::BUCKAROO_PENDING_PAYMENT, self::BUCKAROO_SUCCESS])) {
            return true;
        }

        return false;
    }

    public function isRedirectRequired()
    {
        return $this->response->hasRedirect();
    }

    public function getRedirectUrl()
    {
        return $this->response->getRedirectUrl();
    }

    public function getResponse()
    {
        return $this->response;
    }

    private function setPostVariable($key)
    {
        if (Tools::getValue($key)) {
            return Tools::getValue($key);
        } else {
            return null;
        }
    }

    public function getCartIdAndReferenceId($show = false)
    {
        $e = explode('_', urldecode($this->invoicenumber));
        if (!empty($e[1])) {
            list($reference, $cartId) = $e;
        } else {
            $cartId = 0;
            $reference = $this->invoicenumber;
        }
        if ($show == 'cartId') {
            return (int) $cartId;
        }

        return $reference;
    }

    public function getCartId()
    {
        return $this->getCartIdAndReferenceId('cartId');
    }

    public function getReferenceId()
    {
        return $this->getCartIdAndReferenceId('reference');
    }
}
