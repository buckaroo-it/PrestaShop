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
include_once _PS_MODULE_DIR_ . 'buckaroo3/api/paymentmethods/responsefactory.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/library/logger.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/common.php';

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

        $response = ResponseFactory::getResponse();

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

            $id_order = Order::getOrderByCartId($response->getCartId());
            $this->logger->logInfo('Update the order', 'Order ID: ' . $id_order);

            if ($response->hasSucceeded()) {
                $cart = new Cart($response->getCartId());
                $customer = new Customer($cart->id_customer);

                if (!Validate::isLoadedObject($customer)) {
                    $this->logger->logError('Load a customer', 'Failed to load the customer with ID: ' . $cart->id_customer);
                    Tools::redirect('index.php?controller=order&step=1');
                    exit;
                }

                $this->context->cart->delete();
                $redirectUrl = $this->context->link->getPageLink('order-confirmation', null, null, [
                    'id_cart' => $cart->id,
                    'id_module' => $this->module->id,
                    'id_order' => $id_order,
                    'key' => $customer->secure_key,
                    'success' => 'true',
                ]);
                $this->logger->logInfo('Redirecting to order confirmation', ['url' => $redirectUrl]);
                Tools::redirect($redirectUrl);
            } else {
                $cookie->statusMessage = '';
                if (($response->payment_method == 'afterpayacceptgiro'
                        || $response->payment_method == 'afterpaydigiaccept')
                    && $response->statusmessage) {
                    $cookie->statusMessage = $response->statusmessage;
                }
                $this->logger->logError('Payment failed', ['statusMessage' => $cookie->statusMessage]);
                Tools::redirect('index.php?fc=module&module=buckaroo3&controller=error');
                exit;
            }
        } else {
            $cookie->statusMessage = 'Not valid response';
            $this->logger->logError('Invalid payment response');
            Tools::redirect('index.php?fc=module&module=buckaroo3&controller=error');
        }
        exit;
    }
}
