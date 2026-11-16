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

namespace Buckaroo\PrestaShop\Src\Service;

use Buckaroo\PrestaShop\Src\Repository\RawBuckarooFeeRepository;
use Buckaroo\PrestaShop\Src\Repository\RawPaymentAttemptRepository;
use PrestaShop\Decimal\DecimalNumber;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Creates the PrestaShop order for a payment attempt once Buckaroo confirmed the payment.
 *
 * The push, the customer return and a direct payment response may all arrive for the
 * same attempt, at the same time. A MySQL lock per cart makes sure only one of them
 * creates the order; the others find it and continue with it.
 *
 * The order is created in the "awaiting payment" state, like the order-first flow
 * does before the payment. The caller then moves it to paid with the existing push
 * and return handling, so the payment booking and the payment fee stay the same.
 */
class OrderAfterPaymentService
{
    private const LOCK_TIMEOUT = 15;

    /** @var \PaymentModule */
    private $module;

    /** @var \Context */
    private $context;

    /** @var RawPaymentAttemptRepository */
    private $attempts;

    /** @var RawBuckarooFeeRepository */
    private $fees;

    /** @var string */
    private $lastError = '';

    public function __construct(
        \PaymentModule $module,
        \Context $context,
        ?RawPaymentAttemptRepository $attempts = null,
        ?RawBuckarooFeeRepository $fees = null
    ) {
        $this->module = $module;
        $this->context = $context;
        $this->attempts = $attempts ?? new RawPaymentAttemptRepository();
        $this->fees = $fees ?? new RawBuckarooFeeRepository();
    }

    /**
     * Create (or find) the order for a paid attempt.
     *
     * @param array<string, mixed> $attempt row of bk_payment_attempt
     *
     * Result keys: id_order; created (this call created it); amount_matches (false: the order
     * is on "Payment error" and must not be marked paid); duplicate (another attempt of this
     * cart created the order already: this payment must not be booked on it)
     *
     * @return array{id_order:int,created:bool,amount_matches:bool,duplicate:bool}|null null when
     *                                                                                  no order could
     *                                                                                  be created; see
     *                                                                                  getLastError()
     */
    public function createOrder(array $attempt, float $paidAmount, string $transactionKey): ?array
    {
        $this->lastError = '';
        $cartId = (int) $attempt['id_cart'];
        $attemptId = (int) $attempt['id_bk_payment_attempt'];
        $reference = (string) $attempt['reference'];

        if (!$this->lock($cartId)) {
            $this->lastError = 'Another request is creating the order for cart ' . $cartId;

            return null;
        }

        try {
            $existing = (int) \Order::getIdByCartId($cartId);
            if ($existing > 0) {
                // Re-read the attempt: the row the caller loaded may predate the order.
                $current = $this->attempts->findById($attemptId) ?? $attempt;
                $sameAttempt = (int) $current['id_order'] === $existing;
                $amountMatches = !RawPaymentAttemptRepository::hasAmountMismatch($current);
                if (!$sameAttempt) {
                    $this->attempts->markPaid($attemptId, $existing, $transactionKey);
                    // Paid twice for one cart (two tabs, or a retry while the first payment was
                    // still pending): the order exists already, so the merchant must refund this one.
                    $this->addMessage($existing, sprintf(
                        'Buckaroo: another payment (%s, transaction key %s, amount %s) was received for this cart. Refund it if it was not intended.',
                        $reference,
                        $transactionKey,
                        $this->formatAmount($paidAmount)
                    ));
                }

                return ['id_order' => $existing, 'created' => false, 'amount_matches' => $amountMatches, 'duplicate' => !$sameAttempt];
            }

            $cart = new \Cart($cartId);
            if (!\Validate::isLoadedObject($cart) || (int) $cart->nbProducts() <= 0) {
                $this->lastError = 'Cart ' . $cartId . ' cannot be loaded or is empty';

                return null;
            }

            $customer = new \Customer((int) $cart->id_customer);
            if (!\Validate::isLoadedObject($customer)) {
                $this->lastError = 'Customer of cart ' . $cartId . ' cannot be loaded';

                return null;
            }

            $this->prepareContext($cart, $customer);

            $fee = $this->fees->getFeeByReference($reference);
            $feeIncl = is_array($fee) ? (float) $fee['buckaroo_fee_tax_incl'] : 0.0;
            $feeExcl = is_array($fee) ? (float) $fee['buckaroo_fee_tax_excl'] : 0.0;
            $expected = (float) $cart->getOrderTotal(true, \Cart::BOTH) + $feeIncl;
            $amountMatches = OrderAfterPayment::amountMatches($paidAmount, $expected);

            $this->module->validateOrder(
                $cartId,
                (int) \Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT'),
                round($expected, 2),
                $this->paymentLabel($attempt),
                null,
                [],
                (int) $cart->id_currency,
                false,
                $customer->secure_key
            );

            $orderId = (int) $this->module->currentOrder;
            if ($orderId <= 0) {
                $this->lastError = 'validateOrder did not create an order for cart ' . $cartId;

                return null;
            }

            if ($feeIncl > 0) {
                $this->fees->assignOrder($reference, $orderId);
                $this->addFeeToOrderTotals($orderId, $cart, $feeExcl, $feeIncl);
            }

            $this->attempts->markPaid($attemptId, $orderId, $transactionKey, !$amountMatches);
            $this->addMessage($orderId, sprintf(
                'Order created after a successful Buckaroo payment. Invoice: %s_%d. Transaction key: %s',
                $reference,
                $cartId,
                $transactionKey
            ));

            if (!$amountMatches) {
                $this->markPaymentError($orderId, $paidAmount, $expected);
            }

            return ['id_order' => $orderId, 'created' => true, 'amount_matches' => $amountMatches, 'duplicate' => false];
        } catch (\Throwable $e) {
            $this->lastError = 'Order creation failed for cart ' . $cartId . ': ' . $e->getMessage();

            return null;
        } finally {
            $this->unlock($cartId);
        }
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * The push has no customer session; validateOrder takes the cart from the context.
     */
    private function prepareContext(\Cart $cart, \Customer $customer): void
    {
        $context = $this->context;
        $context->cart = $cart;
        $context->customer = $customer;
        $context->currency = new \Currency((int) $cart->id_currency);
        if ((int) $cart->id_lang > 0) {
            $context->language = new \Language((int) $cart->id_lang);
        }
    }

    /**
     * @param array<string, mixed> $attempt
     */
    private function paymentLabel(array $attempt): string
    {
        $label = trim((string) ($attempt['payment_label'] ?? ''));

        return $label !== '' ? $label : (string) $attempt['payment_method'];
    }

    /**
     * Same totals as the order-first flow sets after adding the fee (Checkout::updateOrderTotals).
     */
    private function addFeeToOrderTotals(int $orderId, \Cart $cart, float $feeExcl, float $feeIncl): void
    {
        $totalExcl = (new DecimalNumber((string) $cart->getOrderTotal(false, \Cart::BOTH)))
            ->plus(new DecimalNumber((string) $feeExcl));
        $totalIncl = (new DecimalNumber((string) $cart->getOrderTotal(true, \Cart::BOTH)))
            ->plus(new DecimalNumber((string) $feeIncl));

        $order = new \Order($orderId);
        $order->total_paid_tax_excl = (float) $totalExcl->toPrecision(2);
        $order->total_paid_tax_incl = (float) $totalIncl->toPrecision(2);
        $order->total_paid = (float) $totalIncl->toPrecision(2);
        $order->total_paid_real = (float) $totalIncl->toPrecision(2);
        $order->update();
    }

    /**
     * The cart changed after the payment started: the order no longer matches what was
     * paid. Keep the order (the money was received) but put it on "Payment error".
     */
    private function markPaymentError(int $orderId, float $paid, float $expected): void
    {
        $this->addMessage($orderId, sprintf(
            'Buckaroo: the paid amount %s does not match the order total %s. The cart was changed during the payment. Check the order before shipping.',
            $this->formatAmount($paid),
            $this->formatAmount($expected)
        ));

        $errorState = (int) \Configuration::get('PS_OS_ERROR');
        if ($errorState <= 0) {
            return;
        }

        $history = new \OrderHistory();
        $history->id_order = $orderId;
        $history->changeIdOrderState($errorState, $orderId, true);
        $history->addWithemail(false);
    }

    private function addMessage(int $orderId, string $text): void
    {
        $message = new \Message();
        $message->id_order = $orderId;
        $message->message = $text;
        $message->private = true;
        $message->add();
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function lock(int $cartId): bool
    {
        return (new CartOrderLock())->acquire($cartId, self::LOCK_TIMEOUT);
    }

    private function unlock(int $cartId): void
    {
        (new CartOrderLock())->release($cartId);
    }
}
