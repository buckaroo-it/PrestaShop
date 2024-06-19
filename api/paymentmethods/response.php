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

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class Response extends BuckarooAbstract
{
    private $received = false;
    private $validated = false;
    private $test = true;
    private $signature;
    private $isPush;
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
    public $transactions;
    public $parameterError;
    protected ?TransactionResponse $response = null;
    protected $logger;

    public function __construct(TransactionResponse $response = null)
    {
        $this->logger  = new Logger(Logger::INFO, 'response');
        $this->logger->logInfo("\n\n\n\n***************** Response start ***********************");

        if ($response) {
            $this->response = $response;
            $this->logger->logInfo('Response object provided directly');
        } else {
            $this->isPush = $this->isPushRequest();
            $this->received = true;
            $this->logger->logInfo('Response determined to be a push request');
            $this->parsePushRequest();
        }
    }

    protected function getPaymentCode(string $configCode): string
    {
        return $configCode === 'Capayable' ? 'in3' : $configCode;
    }

    private function parsePushRequest()
    {
        if (!$this->isPushRequest()) {
            $this->logger->logInfo('Not a push request');
            return false;
        }

        $this->payment = $this->setPostVariable('brq_payment');
        $this->payment_method = $this->getPaymentCode($this->setPostVariable('brq_payment_method') ?? $this->setPostVariable('brq_transaction_method'));
        $this->statuscode = $this->setPostVariable('brq_statuscode');
        $this->statusmessage = $this->setPostVariable('brq_statusmessage');
        $this->statuscode_detail = $this->setPostVariable('brq_statuscode_detail');
        $this->brq_relatedtransaction_partialpayment = $this->setPostVariable('brq_relatedtransaction_partialpayment');
        $this->brq_transaction_type = $this->setPostVariable('brq_transaction_type');
        $this->brq_relatedtransaction_refund = $this->setPostVariable('brq_relatedtransaction_refund');
        $this->invoice = $this->setPostVariable('brq_invoicenumber');
        $this->invoicenumber = $this->setPostVariable('brq_invoicenumber');
        $this->amount = $this->setPostVariable('brq_amount');
        $this->amount_credit = $this->setPostVariable('brq_amount_credit');
        $this->currency = $this->setPostVariable('brq_currency');
        $this->test = $this->setPostVariable('brq_test');
        $this->timestamp = $this->setPostVariable('brq_timestamp');
        $this->transactions = $this->setPostVariable('brq_transactions');
        $this->signature = $this->setPostVariable('brq_signature');

        $this->logger->logInfo('Parsed push request', [
            'statuscode' => $this->statuscode,
            'statusmessage' => $this->statusmessage,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'timestamp' => $this->timestamp
        ]);

        if (!empty($this->statuscode)) {
            $responseArray = $this->responseCodes[(int)$this->statuscode];
            $this->status = $responseArray['status'];
            $this->message = $responseArray['message'];
        }
    }

    public function isSuccess(): bool
    {
        return $this->response->isSuccess();
    }

    public function isFailed(): bool
    {
        return $this->response->isFailed();
    }

    public function isCanceled(): bool
    {
        return $this->response->isCanceled();
    }

    public function isAwaitingConsumer(): bool
    {
        return $this->response->isAwaitingConsumer();
    }

    public function isPendingProcessing(): bool
    {
        return $this->response->isPendingProcessing();
    }

    public function isWaitingOnUserInput(): bool
    {
        return $this->response->isWaitingOnUserInput();
    }

    public function isRejected(): bool
    {
        return $this->response->isRejected();
    }

    private function isPushRequest(): bool
    {
        return (bool)Tools::getValue('brq_statuscode');
    }

    public function getServiceParameters()
    {
        return $this->response->getServiceParameters();
    }

    public function getStatuscode()
    {
        return $this->response->statuscode;
    }

    public function getStatusmessage()
    {
        return $this->response->statusmessage;
    }

    public function getAmount()
    {
        return $this->response->amount;
    }

    public function getBrqRelatedtransactionPartialpayment()
    {
        return $this->brq_relatedtransaction_partialpayment;
    }

    public function hasSomeError(): bool
    {
        return $this->response->hasSomeError();
    }

    public function getSomeError()
    {
        return $this->response->getSomeError();
    }

    public function isTest(): bool
    {
        return $this->response->get('IsTest') === true;
    }

    public function isValid(): bool
    {
        if (!$this->validated) {
            if ($this->isPush) {
                $buckaroo = new BuckarooClient(Configuration::get('BUCKAROO_MERCHANT_KEY'), Configuration::get('BUCKAROO_SECRET_KEY'));
                try {
                    $reply_handler = new ReplyHandler($buckaroo->client()->config(), $_POST);
                    $reply_handler->validate();
                    $this->validated = $reply_handler->isValid();
                    $this->logger->logInfo('Push request validated successfully');
                } catch (Exception $e) {
                    $this->logger->logError('Push request validation failed', ['exception' => $e->getMessage()]);
                }
            } elseif ($this->response) {
                $this->validated = !$this->response->isValidationFailure();
                $this->logger->logInfo('Response validation status', ['validated' => $this->validated]);
            }
        }

        return $this->validated;
    }

    public function hasSucceeded(): bool
    {
        if (isset($this->response)) {
            try {
                if ($this->isValid()) {
                    if ($this->isPendingProcessing() || $this->isAwaitingConsumer() || $this->isWaitingOnUserInput() || $this->isSuccess()) {
                        $this->logger->logInfo('Response has succeeded');
                        return true;
                    }
                }
            } catch (Exception $e) {
                $this->logger->logError('Exception while checking success', ['exception' => $e->getMessage()]);
            }
        } elseif (in_array($this->status, [self::BUCKAROO_PENDING_PAYMENT, self::BUCKAROO_SUCCESS])) {
            $this->logger->logInfo('Response status indicates success');
            return true;
        }

        $this->logger->logInfo('Response has not succeeded');
        return false;
    }

    public function isRedirectRequired(): bool
    {
        return $this->response->hasRedirect();
    }

    public function getRedirectUrl(): string
    {
        return $this->response->getRedirectUrl();
    }

    public function getResponse(): ?TransactionResponse
    {
        return $this->response;
    }

    private function setPostVariable($key)
    {
        return Tools::getValue($key) ?? null;
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
        return $show == 'cartId' ? (int)$cartId : $reference;
    }

    public function getCartId(): int
    {
        return $this->getCartIdAndReferenceId('cartId');
    }

    public function getReferenceId()
    {
        return $this->getCartIdAndReferenceId('reference');
    }

    public function isPartialPayment(): bool
    {
        return !empty($this->brq_relatedtransaction_partialpayment);
    }

    public function getRemainingAmount(): float
    {
        return $this->response->remaining_amount ?? 0;
    }
}
