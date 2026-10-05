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

use Buckaroo\PrestaShop\Src\Service\BuckarooGroupTransactionService;

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
            // Card details missing – if a giftcard is already applied the customer should
            // choose another payment method for the remaining balance rather than re-apply.
            $alreadyApplied  = (float) ($this->context->cookie->buckaroo_giftcard_applied ?? 0);
            $alreadyRemainder = (float) ($this->context->cookie->buckaroo_giftcard_remainder ?? 0);
            if ($alreadyApplied > 0 && $alreadyRemainder > 0) {
                $this->redirectWithGiftcardApplied($alreadyApplied, $alreadyRemainder);
            } else {
                $this->redirectWithError($this->module->l('Please fill in all giftcard fields.'));
            }
            return;
        }

        $currency  = $this->context->currency;
        $cartTotal = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $groupTransactionService = new BuckarooGroupTransactionService();
        $alreadyPaid = $groupTransactionService->getAlreadyPaid((int) $cart->id);
        $amountToCharge = $groupTransactionService->getRemainingAmount((int) $cart->id, $cartTotal);

        if ($amountToCharge <= 0) {
            $this->redirectWithError(
                $this->module->l('The order is already fully covered by giftcard(s).')
            );
            return;
        }

        // Continue an existing group transaction when applying another giftcard.
        $existingGroupTx = '';
        $cookieCartId = (int) ($this->context->cookie->buckaroo_giftcard_cart_id ?? 0);
        if ($cookieCartId === (int) $cart->id) {
            $existingGroupTx = (string) ($this->context->cookie->buckaroo_giftcard_group_tx ?? '');
        }
        if ($existingGroupTx === '') {
            foreach ($groupTransactionService->getGroupTransactionItems((int) $cart->id) as $row) {
                if (!empty($row['group_transaction_id'])) {
                    $existingGroupTx = (string) $row['group_transaction_id'];
                    break;
                }
            }
        }

        try {
            /** @var \BuckarooGiftCard $giftcardApi */
            $giftcardApi = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_GIFTCARD);

            $giftcardApi->currency        = $currency->iso_code;
            $giftcardApi->amountDebit     = (string) round($amountToCharge, 2);
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

            if ($existingGroupTx !== '') {
                $giftcardApi->OriginalTransactionKey = $existingGroupTx;
            }

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
        if ($groupTransaction === '' && $existingGroupTx !== '') {
            $groupTransaction = $existingGroupTx;
        }
        $remainingAmount  = (float) $response->getRemainderAmount();
        $appliedAmount    = round($amountToCharge - $remainingAmount, 2);
        $totalApplied     = round($alreadyPaid + $appliedAmount, 2);

        $this->logger->logInfo('Giftcard applied', [
            'applied'   => $appliedAmount,
            'total'     => $totalApplied,
            'remainder' => $remainingAmount,
            'group_tx'  => $groupTransaction,
        ]);

        // Persist the partial payment to the DB so the checkout summary can reflect it
        // across page reloads and after Buckaroo push confirmation.
        $groupTransactionService->saveGroupTransaction((int) $cart->id, [
            'transaction_key'      => $transactionKey,
            'group_transaction_id' => $groupTransaction,
            'amount'               => $appliedAmount,
            'currency'             => $currency->iso_code,
            'card_code'            => $cardCode,
            'status'               => 190,
        ]);

        // Also store in session cookies as fast-access cache for this page-load
        $this->context->cookie->buckaroo_giftcard_group_tx  = $groupTransaction;
        $this->context->cookie->buckaroo_giftcard_remainder = (string) $remainingAmount;
        $this->context->cookie->buckaroo_giftcard_applied   = (string) $totalApplied;
        $this->context->cookie->buckaroo_giftcard_tx_key    = $transactionKey;
        $this->context->cookie->buckaroo_giftcard_card_code = $cardCode;
        $this->context->cookie->buckaroo_giftcard_cart_id   = (string) (int) $cart->id;
        $this->context->cookie->write();

        if ($remainingAmount <= 0) {
            $this->completeOrderWithGiftcard($cart, $cartTotal);
        } else {
            $this->redirectWithGiftcardApplied($appliedAmount, $remainingAmount);
        }
    }

    /**
     * Giftcard covers the full cart total: create the order now and mark it paid.
     */
    private function completeOrderWithGiftcard(Cart $cart, float $total): void
    {
        $customer     = new Customer($cart->id_customer);
        $currency     = $this->context->currency;
        $pendingState = (int) Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');

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

        $this->recordGroupTransactionPayments((int) $orderId);
        $this->syncOrderTotalPaidReal((int) $orderId);

        $paidState = (int) Buckaroo3::resolveStatusCode(BuckarooAbstract::BUCKAROO_SUCCESS, (int) $orderId);
        $this->updatePendingOrderStatus((int) $orderId, $paidState, true);

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
