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

include_once __DIR__ . '/../../api/paymentmethods/paymentrequestfactory.php';
include_once __DIR__ . '/../../library/logger.php';
include_once __DIR__ . '/common.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Handles "Apply Gift Card" without creating an order.
 *
 * Calls the Buckaroo giftcard API directly with the card details the customer
 * entered.  If the card covers the whole cart it creates and confirms the order
 * immediately.  If only a partial amount is covered it stores the giftcard
 * state in session cookies and redirects the customer back to checkout step 3
 * so they can pay the remaining balance with another payment method.
 */
class Buckaroo3ApplygiftcardModuleFrontController extends BuckarooCommonController
{
    protected $logger;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger(Logger::INFO, 'applygiftcard');
    }

    public function postProcess()
    {
        $this->logger->logInfo('Apply Giftcard – start');

        $cart = $this->context->cart;

        if (!Validate::isLoadedObject($cart) || $cart->id_customer == 0) {
            $this->redirectWithError(
                $this->module->l('Your shopping session expired. Please start the checkout again.')
            );
            return;
        }

        $cardNumber   = (string) Tools::getValue('giftcard_card_number', '');
        $securityCode = (string) Tools::getValue('giftcard_security_code', '');
        $cardCode     = (string) Tools::getValue('cardCode', '');

        if (empty($cardNumber) || empty($securityCode)) {
            $this->redirectWithError($this->module->l('Please fill in all giftcard fields.'));
            return;
        }

        $currency  = $this->context->currency;
        $cartTotal = (float) $cart->getOrderTotal(true, Cart::BOTH);

        try {
            /** @var \BuckarooGiftCard $giftcardApi */
            $giftcardApi = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_GIFTCARD);

            $giftcardApi->currency        = $currency->iso_code;
            $giftcardApi->amountDebit     = (string) round($cartTotal, 2);
            $giftcardApi->invoiceId       = 'gc_' . $cart->id . '_' . time();
            $giftcardApi->orderId         = 'gc_' . $cart->id;
            $giftcardApi->description     = 'Giftcard';
            $giftcardApi->returnUrl       = $this->context->link->getModuleLink('buckaroo3', 'userreturn', [], true);
            $giftcardApi->pushUrl         = $this->context->link->getModuleLink('buckaroo3', 'return', [], true);
            $giftcardApi->platformName    = 'PrestaShop';
            $giftcardApi->platformVersion = _PS_VERSION_;
            $giftcardApi->moduleVersion   = $this->module->version;
            $giftcardApi->moduleSupplier  = $this->module->author;
            $giftcardApi->moduleName      = $this->module->name;

            $customVars = [
                'cardNumber' => $cardNumber,
                'pin'        => $securityCode,
            ];
            if (!empty($cardCode)) {
                $customVars['name'] = $cardCode;
            }

            $response = $giftcardApi->payDirect($customVars);
        } catch (Exception $e) {
            $this->logger->logError('Giftcard API error: ' . $e->getMessage());
            $this->redirectWithError(
                $this->module->l('The giftcard could not be applied. Please try again.')
            );
            return;
        }

        if (!$response->hasSucceeded()) {
            $error = method_exists($response, 'getSomeError') ? (string) $response->getSomeError() : '';
            if (empty($error)) {
                $error = $this->module->l(
                    'The giftcard could not be applied. Please check your card details and try again.'
                );
            }
            $this->logger->logError('Giftcard pay failed: ' . $error);
            $this->redirectWithError($error);
            return;
        }

        $transactionKey   = ($response->getResponse()) ? (string) $response->getResponse()->getTransactionKey() : '';
        $groupTransaction = (string) ($response->getGroupTransaction() ?? '');
        $remainingAmount  = (float) $response->getRemainderAmount();
        $appliedAmount    = round($cartTotal - $remainingAmount, 2);

        $this->logger->logInfo('Giftcard applied', [
            'applied'   => $appliedAmount,
            'remainder' => $remainingAmount,
            'group_tx'  => $groupTransaction,
        ]);

        // Persist giftcard state so the next payment step can reference it
        $this->context->cookie->buckaroo_giftcard_group_tx  = $groupTransaction;
        $this->context->cookie->buckaroo_giftcard_remainder = (string) $remainingAmount;
        $this->context->cookie->buckaroo_giftcard_applied   = (string) $appliedAmount;
        $this->context->cookie->buckaroo_giftcard_tx_key    = $transactionKey;
        $this->context->cookie->buckaroo_giftcard_card_code = $cardCode;
        $this->context->cookie->write();

        if ($remainingAmount <= 0) {
            $this->completeOrderWithGiftcard($cart, $cartTotal, $transactionKey, $cardCode);
        } else {
            $this->redirectWithGiftcardApplied($appliedAmount, $remainingAmount);
        }
    }

    /**
     * Giftcard covers the full cart total: create the order now and mark it paid.
     */
    private function completeOrderWithGiftcard(
        Cart $cart,
        float $total,
        string $transactionKey,
        string $cardCode
    ): void {
        $customer     = new Customer($cart->id_customer);
        $currency     = $this->context->currency;
        $pendingState = (int) Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');
        $paidState    = (int) Configuration::get('PS_OS_PAYMENT');

        try {
            $this->module->validateOrder(
                (int) $cart->id,
                $pendingState,
                $total,
                $this->module->l('Giftcard'),
                null,
                [],
                (int) $currency->id,
                false,
                $customer->secure_key
            );
        } catch (Exception $e) {
            $this->logger->logError('validateOrder failed: ' . $e->getMessage());
            $this->redirectWithError($e->getMessage());
            return;
        }

        $orderId = $this->module->currentOrder;
        $order   = new Order($orderId);

        $payment                  = new OrderPayment();
        $payment->order_reference = $order->reference;
        $payment->id_currency     = $order->id_currency;
        $payment->conversion_rate = 1;
        $payment->amount          = $total;
        $payment->payment_method  = $cardCode ?: 'giftcard';
        $payment->transaction_id  = $transactionKey;
        $payment->save();

        $order->total_paid_real = $total;
        $order->save();

        $history           = new OrderHistory();
        $history->id_order = $orderId;
        $history->changeIdOrderState($paidState, $orderId);
        $history->add(true);

        $this->clearGiftcardCookies();

        Tools::redirect($this->context->link->getPageLink('order-confirmation', true, null, [
            'id_cart'   => $cart->id,
            'id_module' => $this->module->id,
            'id_order'  => $orderId,
            'key'       => $customer->secure_key,
            'success'   => 'true',
        ]));
    }

    /**
     * Giftcard only partially covers the cart: redirect to checkout with an info message.
     */
    private function redirectWithGiftcardApplied(float $applied, float $remaining): void
    {
        $locale = isset($this->context->currentLocale) ? $this->context->currentLocale : null;
        $iso    = $this->context->currency->iso_code;

        if ($locale && method_exists($locale, 'formatPrice')) {
            $appliedStr   = $locale->formatPrice($applied, $iso);
            $remainingStr = $locale->formatPrice($remaining, $iso);
        } else {
            $sign         = $this->context->currency->sign ?? '€';
            $appliedStr   = $sign . number_format($applied, 2);
            $remainingStr = $sign . number_format($remaining, 2);
        }

        $msg = sprintf(
            $this->module->l('Giftcard applied (%s). Please pay the remaining %s with another payment method.'),
            $appliedStr,
            $remainingStr
        );

        Tools::redirect($this->context->link->getPageLink('order', null, null, [
            'step'                      => 3,
            'buckaroo_giftcard_applied' => 1,
            'buckaroo_giftcard_msg'     => urlencode($msg),
        ]));
    }

    private function redirectWithError(string $msg): void
    {
        Tools::redirect($this->context->link->getPageLink('order', null, null, [
            'step'               => 3,
            'buckaroo_error_msg' => urlencode($msg),
            'buckaroo_error'     => 1,
        ]));
    }
}
