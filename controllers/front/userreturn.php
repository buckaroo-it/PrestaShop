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

use Buckaroo\PrestaShop\Src\Service\ReturnStateSigner;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Buckaroo3UserreturnModuleFrontController extends BuckarooCommonController
{
    private const STATE_PARAMETER = 'bk_state';

    public $ssl = true;
    protected $logger;

    /**
     * True when the browser did not send the shop session cookie. Buckaroo returns the
     * customer with a cross-site POST, and browsers leave out SameSite=Lax cookies there.
     *
     * @var bool
     */
    private $withoutSession = false;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger(Logger::INFO, 'userreturn');
    }

    public function init()
    {
        if (!Tools::getValue(self::STATE_PARAMETER) && !$this->context->cookie->exists()) {
            // Writing the cookie now would replace the customer's session with an empty one.
            $this->withoutSession = true;
            $this->context->cookie->disallowWriting();
        }

        parent::init();
    }

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $this->logger->logInfo("\n\n\n\n***************** User return start ***********************");

        try {
            $state = (string) Tools::getValue(self::STATE_PARAMETER);
            if ($state !== '') {
                $this->handleSignedState($state);

                return;
            }

            $response = ResponseFactory::getResponse();
            $this->handleReturn($response);
        } catch (Throwable $e) {
            $this->logger->logError('User return fatal: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->redirectToCheckoutError($this->defaultErrorMessage());
        }
    }

    private function handleReturn($response): void
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

                if ($response->isPaid()) {
                    $this->markOrderPaidAfterSuccessfulReturn((int) $id_order, $response);
                } else {
                    $this->logger->logInfo('Pending return; order state left for the push', [
                        'orderId' => $id_order,
                        'statuscode' => $response->getStatusCodeValue(),
                    ]);
                }

                $this->finishSuccess($cartId);

                return;
            }

            $msg = $response->statusmessage ?: $this->defaultErrorMessage();
            $this->logger->logError('Payment failed', ['statusMessage' => $msg]);
            $this->finishFailure($cartId, $msg);

            return;
        }

        $this->logger->logError('Payment failed or invalid response');
        $this->finishFailure(
            (int) $response->getCartId(),
            $response->statusmessage ?: $this->defaultErrorMessage()
        );
    }

    /**
     * Second leg of a return that arrived without the session: a same-site GET that carries the cookie.
     */
    private function handleSignedState(string $state): void
    {
        $data = $this->getStateSigner()->verify($state);

        if ($data === null) {
            $this->logger->logError('Invalid or expired return state');
            $this->redirectToCheckoutError($this->defaultErrorMessage());

            return;
        }

        if ($data['outcome'] === ReturnStateSigner::OUTCOME_SUCCESS) {
            $this->finishSuccess($data['cartId']);

            return;
        }

        $this->finishFailure($data['cartId'], $data['message'] ?: $this->defaultErrorMessage());
    }

    /**
     * Session work after a successful return: clear the giftcard state and show the order confirmation.
     */
    private function finishSuccess(int $cartId): void
    {
        if ($this->withoutSession) {
            $this->redirectWithState($cartId, ReturnStateSigner::OUTCOME_SUCCESS);
        }

        $cart = new Cart($cartId);
        $customer = new Customer((int) $cart->id_customer);
        $id_order = $cartId > 0 ? (int) Order::getIdByCartId($cartId) : 0;

        if ($id_order <= 0 || !Validate::isLoadedObject($customer)) {
            $this->logger->logError('Missing cart/order/customer on successful return', [
                'cartId' => $cartId,
                'orderId' => $id_order,
            ]);
            Tools::redirect('index.php?controller=order&step=1');
            exit;
        }

        $this->clearGiftcardCookies();

        if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)) {
            try {
                $this->context->cart->delete();
            } catch (Throwable $e) {
                $this->logger->logError('Cart delete failed: ' . $e->getMessage());
            }
        }

        $redirectUrl = $this->context->link->getPageLink('order-confirmation', true, null, [
            'id_cart' => $cartId,
            'id_module' => $this->module->id,
            'id_order' => $id_order,
            'key' => $customer->secure_key,
            'success' => 'true',
        ]);
        $this->logger->logInfo('Redirecting to order confirmation', ['url' => $redirectUrl]);
        Tools::redirect($redirectUrl);
        exit;
    }

    /**
     * Session work after a failed return: give the customer their cart back and show the message.
     */
    private function finishFailure(int $cartId, string $msg): void
    {
        if ($this->withoutSession) {
            $this->redirectWithState($cartId, ReturnStateSigner::OUTCOME_FAILURE, $msg);
        }

        $this->setCartCookie($cartId);

        $cookie = new Cookie('ps');
        $cookie->statusMessage = $msg;

        $this->redirectToCheckoutError($msg);
    }

    private function redirectWithState(int $cartId, string $outcome, string $msg = ''): void
    {
        $this->logger->logInfo('Return without session cookie; continuing with a signed state', [
            'cartId' => $cartId,
            'outcome' => $outcome,
        ]);

        Tools::redirect($this->context->link->getModuleLink('buckaroo3', 'userreturn', [
            self::STATE_PARAMETER => $this->getStateSigner()->sign($cartId, $outcome, $msg),
        ], true));
        exit;
    }

    private function getStateSigner(): ReturnStateSigner
    {
        return new ReturnStateSigner(_COOKIE_KEY_);
    }

    private function defaultErrorMessage(): string
    {
        return $this->module->l('Your payment was unsuccessful. Please try again or choose another payment method.');
    }

    private function redirectToCheckoutError(string $msg): void
    {
        try {
            $this->context->cookie->__set('buckaroo_error_msg', $msg);
            $this->context->cookie->write();
        } catch (Throwable $e) {
            // Continue with query-string fallback.
        }

        Tools::redirect($this->context->link->getPageLink('order', true, null, [
            'step' => 3,
            'buckaroo_error_msg' => urlencode($msg),
            'buckaroo_error' => 1,
        ]));
        exit;
    }

    private function setCartCookie($cartId)
    {
        $cartId = (int) $cartId;
        if ($cartId <= 0) {
            return;
        }

        $oldCart = new Cart($cartId);
        if (!$this->cartBelongsToSession($oldCart)) {
            $this->logger->logError('Cart does not belong to the current customer; cart not restored', [
                'cartId' => $cartId,
            ]);

            return;
        }

        $orderId = Order::getIdByCartId($cartId);

        if ($orderId) {
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

    private function cartBelongsToSession(Cart $cart): bool
    {
        $customerId = (int) $cart->id_customer;

        return $customerId > 0 && $customerId === (int) $this->context->cookie->id_customer;
    }
}
