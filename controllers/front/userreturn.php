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
include_once __DIR__ . '/../../api/paymentmethods/responsefactory.php';
include_once __DIR__ . '/../../library/logger.php';
include_once __DIR__ . '/common.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class Buckaroo3UserreturnModuleFrontController extends BuckarooCommonController
{
    public $ssl = true;
    protected $logger;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger(Logger::INFO, 'userreturn');
    }

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $cookie = new Cookie('ps');
        $this->logger->logInfo("\n\n\n\n***************** User return start ***********************");

        try {
            $response = ResponseFactory::getResponse();
            $this->handleReturn($response, $cookie);
        } catch (\Throwable $e) {
            $this->logger->logError('User return fatal: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->redirectToCheckoutError(
                $this->module->l('Your payment was unsuccessful. Please try again or choose another payment method.')
            );
        }
    }

    private function handleReturn($response, Cookie $cookie): void
    {
        if ($response->isValid()) {
            $this->logger->logInfo('Payment request succeeded');

            if (!empty($response->payment_method)
                && ($response->payment_method == 'paypal')
                && !empty($response->statuscode)
                && ($response->statuscode == 791)
            ) {
                $response->statuscode = 890;
                $response->status = $response::BUCKAROO_CANCELED;
            }

            $cartId = (int) $response->getCartId();
            $id_order = $cartId > 0 ? (int) Order::getIdByCartId($cartId) : 0;
            $this->logger->logInfo('Update the order', 'Order ID: ' . $id_order);

            if ($response->hasSucceeded()) {
                if ($cartId <= 0 || $id_order <= 0) {
                    $this->logger->logError('Missing cart/order on successful return', [
                        'cartId' => $cartId,
                        'orderId' => $id_order,
                    ]);
                    $this->redirectToCheckoutError(
                        $this->module->l('Your payment was received, but the order could not be loaded. Please contact the shop.')
                    );
                    return;
                }

                $cart = new Cart($cartId);
                $customer = new Customer((int) $cart->id_customer);

                if (!Validate::isLoadedObject($customer)) {
                    $this->logger->logError('Load a customer', 'Failed to load the customer with ID: ' . $cart->id_customer);
                    Tools::redirect('index.php?controller=order&step=1');
                    exit;
                }

                $this->markOrderPaidAfterSuccessfulReturn((int) $id_order, $response);
                $this->clearGiftcardCookies();

                if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)) {
                    try {
                        $this->context->cart->delete();
                    } catch (\Throwable $e) {
                        $this->logger->logError('Cart delete failed: ' . $e->getMessage());
                    }
                }

                $redirectUrl = $this->context->link->getPageLink('order-confirmation', true, null, [
                    'id_cart'   => $cartId,
                    'id_module' => $this->module->id,
                    'id_order'  => $id_order,
                    'key'       => $customer->secure_key,
                    'success'   => 'true',
                ]);
                $this->logger->logInfo('Redirecting to order confirmation', ['url' => $redirectUrl]);
                Tools::redirect($redirectUrl);
                exit;
            }

            $this->setCartCookie($cartId);

            $cookie->statusMessage = $response->statusmessage ?: $this->module->l(
                'Your payment was unsuccessful. Please try again or choose another payment method.'
            );

            $this->logger->logError('Payment failed', ['statusMessage' => $cookie->statusMessage]);
            $msg = $response->statusmessage ?: $this->module->l(
                'Your payment was unsuccessful. Please try again or choose another payment method.'
            );

            $this->redirectToCheckoutError($msg);
            return;
        }

        $this->setCartCookie((int) $response->getCartId());
        $this->logger->logError('Payment failed or invalid response');
        $this->redirectToCheckoutError(
            $response->statusmessage ?: $this->module->l(
                'Your payment was unsuccessful. Please try again or choose another payment method.'
            )
        );
    }

    private function redirectToCheckoutError(string $msg): void
    {
        try {
            $this->context->cookie->__set('buckaroo_error_msg', $msg);
            $this->context->cookie->write();
        } catch (\Throwable $e) {
            // Continue with query-string fallback.
        }

        Tools::redirect($this->context->link->getPageLink('order', true, null, [
            'step'               => 3,
            'buckaroo_error_msg' => urlencode($msg),
            'buckaroo_error'     => 1,
        ]));
        exit;
    }

    private function setCartCookie($cartId)
    {
        $cartId = (int) $cartId;
        if ($cartId <= 0) {
            return;
        }

        $orderId = Order::getIdByCartId($cartId);

        if ($orderId) {
            $oldCart    = new Cart($cartId);
            $duplication = $oldCart->duplicate();

            if ($duplication && Validate::isLoadedObject($duplication['cart']) && $duplication['success']) {
                $this->context->cookie->id_cart = $duplication['cart']->id;
                $this->context->cookie->write();
            } else {
                $this->logger->logError('Cart duplication failed');
            }
        } else {
            $this->context->cookie->id_cart = $cartId;
            $this->context->cookie->write();
        }
    }
}
