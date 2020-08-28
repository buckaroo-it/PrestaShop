<?php
/**
 *
 *
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

class Buckaroo3UserreturnModuleFrontController extends BuckarooCommonController
{

    public $ssl = true;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $cookie   = new Cookie('ps');
        $logger   = new Logger(Logger::INFO, 'return');
        $response = ResponseFactory::getResponse();
        $logger->logDebug('Checkout response', $response);
        if ($response->isValid()) {
            $logger->logInfo('Payment request succeeded');

            $id_order = Order::getOrderByCartId($response->getCartId());
            $logger->logInfo('Update the order', "Order ID: " . $id_order);
            if ($response->hasSucceeded()) {
                $cart     = new Cart($response->getCartId());
                $customer = new Customer($cart->id_customer);
                if (!Validate::isLoadedObject($customer)) {
                    $logger->logError("Load a customer", 'Failed to load the customer with ID: ' . $cart->id_customer);
                    Tools::redirect('index.php?controller=order&step=1');
                    exit();
                }

                $payment_method = $response->payment_method;
                if($payment_method=='bancontactmrcash'){
                    $payment_method='MISTERCASH';
                }

                $this->context->cart->delete();
                $redirectUrl = 'http' . ((Tools::getIsset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") ? 's' : '') . '://' . $_SERVER["SERVER_NAME"] . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $id_order . '&key=' . $customer->secure_key . '&success=true';//phpcs:ignore
                Tools::redirect($redirectUrl);
            } else {
                $cookie->statusMessage = '';
                if (($response->payment_method == 'afterpayacceptgiro'
                    || $response->payment_method == 'afterpaydigiaccept')
                    && $response->statusmessage) {
                    $cookie->statusMessage = $response->statusmessage;
                }
                Tools::redirect('index.php?fc=module&module=buckaroo3&controller=error');
                exit();
            }
        }
        exit();
    }
}
