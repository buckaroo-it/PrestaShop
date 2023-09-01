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

use PrestaShop\Decimal\Number;

include_once _PS_MODULE_DIR_ . 'buckaroo3/api/paymentmethods/paymentrequestfactory.php';

abstract class Checkout
{
    protected $customVars = [];

    public const CHECKOUT_TYPE_PAYPAL = 'paypal';
    public const CHECKOUT_TYPE_IDEAL = 'ideal';
    public const CHECKOUT_TYPE_PAYBYBANK = 'paybybank';
    public const CHECKOUT_TYPE_SEPADIRECTDEBIT = 'sepadirectdebit';
    public const CHECKOUT_TYPE_GIROPAY = 'giropay';
    public const CHECKOUT_TYPE_KBC = 'kbc';
    public const CHECKOUT_TYPE_MISTERCASH = 'bancontactmrcash';
    public const CHECKOUT_TYPE_GIFTCARD = 'giftcard';
    public const CHECKOUT_TYPE_CREDITCARD = 'creditcard';
    public const CHECKOUT_TYPE_SOFORTBANKING = 'sofortueberweisung';
    public const CHECKOUT_TYPE_TRANSFER = 'transfer';
    public const CHECKOUT_TYPE_AFTERPAY = 'afterpay';
    public const CHECKOUT_TYPE_KLARNA = 'klarna';
    public const CHECKOUT_TYPE_APPLEPAY = 'applepay';
    public const CHECKOUT_TYPE_BELFIUS = 'belfius';
    public const CHECKOUT_TYPE_IDIN = 'idin';
    public const CHECKOUT_TYPE_IN3 = 'in3';
    public const CHECKOUT_TYPE_IN3Old = 'in3Old';
    public const CHECKOUT_TYPE_BILLINK = 'billink';
    public const CHECKOUT_TYPE_EPS = 'eps';
    public const CHECKOUT_TYPE_PAYCONIQ = 'payconiq';
    public const CHECKOUT_TYPE_PAYPEREMAIL = 'payperemail';
    public const CHECKOUT_TYPE_PRZELEWY24 = 'przelewy24';
    public const CHECKOUT_TYPE_TINKA = 'tinka';
    public const CHECKOUT_TYPE_TRUSTLY = 'trustly';

    // Request types (Payment Methods).
    public static $payment_method_type = [
        Checkout::CHECKOUT_TYPE_PAYPAL => 'PayPal',
        Checkout::CHECKOUT_TYPE_IDEAL => 'IDeal',
        Checkout::CHECKOUT_TYPE_PAYBYBANK => 'PayByBank',
        Checkout::CHECKOUT_TYPE_SEPADIRECTDEBIT => 'SepaDirectdebit',
        Checkout::CHECKOUT_TYPE_GIROPAY => 'Giropay',
        Checkout::CHECKOUT_TYPE_KBC => 'Kbc',
        Checkout::CHECKOUT_TYPE_MISTERCASH => 'MisterCash',
        Checkout::CHECKOUT_TYPE_GIFTCARD => 'GiftCard',
        Checkout::CHECKOUT_TYPE_CREDITCARD => 'CreditCard',
        Checkout::CHECKOUT_TYPE_SOFORTBANKING => 'Sofortbanking',
        Checkout::CHECKOUT_TYPE_TRANSFER => 'Transfer',
        Checkout::CHECKOUT_TYPE_AFTERPAY => 'AfterPay',
        Checkout::CHECKOUT_TYPE_KLARNA => 'Klarna',
        Checkout::CHECKOUT_TYPE_APPLEPAY => 'ApplePay',
        Checkout::CHECKOUT_TYPE_BELFIUS => 'Belfius',
        Checkout::CHECKOUT_TYPE_IDIN => 'Idin',
        Checkout::CHECKOUT_TYPE_IN3 => 'In3',
        Checkout::CHECKOUT_TYPE_IN3Old => 'In3Old',
        Checkout::CHECKOUT_TYPE_BILLINK => 'Billink',
        Checkout::CHECKOUT_TYPE_EPS => 'Eps',
        Checkout::CHECKOUT_TYPE_PAYCONIQ => 'Payconiq',
        Checkout::CHECKOUT_TYPE_PAYPEREMAIL => 'PayPerEmail',
        Checkout::CHECKOUT_TYPE_PRZELEWY24 => 'Przelewy24',
        Checkout::CHECKOUT_TYPE_TINKA => 'Tinka',
        Checkout::CHECKOUT_TYPE_TRUSTLY => 'Trustly',
    ];

    // protected $current_order;
    protected $payment_request;
    protected $payment_response;
    /* @var $cart CartCore */
    protected $cart;
    protected $customer;
    protected $invoice_address;
    protected $shipping_address;
    protected $products;
    protected $reference;

    /**
     * @param mixed $reference
     */
    public function setReference($reference)
    {
        $this->reference = $reference;
    }

    /**
     * @return mixed
     */
    public function getReference()
    {
        return $this->reference;
    }

    public $returnUrl;
    public $pushUrl;

    public function __construct($cart)
    {
        $this->initialize();

        $this->cart = $cart;
        $this->customer = new Customer($cart->id_customer);
        $this->invoice_address = new Address((int) $cart->id_address_invoice);
        $this->shipping_address = null;
        if ($cart->id_address_invoice != $cart->id_address_delivery) {
            $this->shipping_address = new Address((int) $cart->id_address_delivery);
        }
        $this->products = $this->cart->getProducts();
    }

    abstract protected function initialize();

    protected function setCheckout()
    {
        $currency = new Currency((int) $this->cart->id_currency);
        $this->payment_request->amountDebit = $originalAmount =
            (string) ((float) $this->cart->getOrderTotal(true, Cart::BOTH));
        $payment_method = Tools::getValue('method');
        if ($payment_method == 'bancontactmrcash') {
            $payment_method = 'MISTERCASH';
        }

        $buckarooFee = $this->getBuckarooFee();
        if ($buckarooFee > 0) {
            $this->updateOrderFee($buckarooFee);
        }

        $this->payment_request->currency = $currency->iso_code;
        $this->payment_request->description = Configuration::get('BUCKAROO_TRANSACTION_LABEL');
        $reference = $this->reference . '_' . $this->cart->id;
        $this->payment_request->invoiceId = $reference;
        $this->payment_request->orderId = $reference;
        $this->payment_request->returnUrl = $this->returnUrl;
        $this->payment_request->pushUrl = $this->pushUrl;
    }

    public function getBuckarooFee()
    {
        $payment_method = Tools::getValue('method');
        if ($payment_method == 'bancontactmrcash') {
            $payment_method = 'MISTERCASH';
        }

        if ($buckarooFee = Config::get('BUCKAROO_' . Tools::strtoupper($payment_method) . '_FEE')) {
            // Remove any whitespace from the fee.
            $buckarooFee = trim($buckarooFee);

            if (strpos($buckarooFee, '%') !== false) {
                // The fee includes a percentage sign, so treat it as a percentage.
                // Remove the percentage sign and convert the remaining value to a float.
                $buckarooFee = str_replace('%', '', $buckarooFee);
                $buckarooFee = (float) $this->payment_request->amountDebit * ((float) $buckarooFee / 100);
            } else {
                $buckarooFee = (float) $buckarooFee;
            }

            return $buckarooFee;
        }
    }

    public function updateOrderFee($buckarooFee)
    {
        $this->payment_request->amountDebit = (string) ((float) $this->payment_request->amountDebit + $buckarooFee);
        $currency = new Currency((int) $this->cart->id_currency);
        Db::getInstance()->insert('buckaroo_fee', [
            'reference' => $this->reference,
            'id_cart' => $this->cart->id,
            'buckaroo_fee' => $buckarooFee,
            'currency' => $currency->iso_code,
        ]);

        $orderFeeNumber = new Number((string) 0);
        $originalAmount = (string) ((float) $this->cart->getOrderTotal(true, Cart::BOTH));
        $totalPrice = new Number((string) ($originalAmount + $buckarooFee));
        $orderFeeNumber->plus($totalPrice);

        $orderid = Order::getOrderByCartId($this->cart->id);

        $order = new Order($orderid);

        $order->total_paid_tax_excl = $orderFeeNumber->plus(new Number((string) $order->total_paid_tax_excl));
        $order->total_paid_tax_incl = $orderFeeNumber->plus(new Number((string) $order->total_paid_tax_incl));
        $order->total_paid = $totalPrice->toPrecision(2);
        $order->update();
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->pay();
    }

    public function isRequestSucceeded()
    {
        if (!empty($this->payment_response) && $this->payment_response->hasSucceeded()) {
            return true;
        }

        return false;
    }

    abstract public function isRedirectRequired();

    abstract public function isVerifyRequired();

    public function doRedirect($redirect_url = null)
    {
        if (is_null($redirect_url)) {
            $redirect_url = $this->payment_response->getRedirectUrl();
        }
        Tools::redirect($redirect_url);
        exit(0);
    }

    public function getStatusCode()
    {
        if (isset($this->payment_response, $this->payment_response->statuscode)) {
            return $this->payment_response->statuscode;
        }

        return 0;
    }

    public function getResponse()
    {
        if (isset($this->payment_response)) {
            return $this->payment_response;
        }

        return null;
    }

    /**
     * Given an checkout_type_id, return an instance of that subclass.
     *
     * @param int checkout_type_id
     * @param array $data
     *
     * @return Address subclass
     */
    final public static function getInstance($payment_method, $cart)
    {
        $class_name = self::$payment_method_type[$payment_method] . 'Checkout';
        checkoutautoload($class_name); // Try to find class in api directory

        if (!class_exists($class_name)) {
            throw new Exception('Payment method not found', '1'); // TODO: ExceptionPayment
        }

        return new $class_name($cart);
    }

    /**
     * Given an checkout_type_id, return an instance of that subclass.
     *
     * @param int checkout_type_id
     * @param array $data
     *
     * @return Address subclass
     */
    final public static function getInstanceRefund($payment_method)
    {
        $payment_method = Tools::strtolower($payment_method);
        $class_name = self::$payment_method_type[$payment_method] . 'Checkout';
        checkoutautoload($class_name); // Try to find class in api directory
        if (!class_exists($class_name)) {
            throw new Exception('Payment method not found', '1'); // TODO: ExceptionPayment
        }

        return new $class_name(null);
    }

    /**
     * Split address to parts
     *
     * @param string $address
     *
     * @return array
     */
    protected function getAddressComponents($address)
    {
        $result = [];
        $result['house_number'] = '';
        $result['number_addition'] = '';

        $address = str_replace(['?', '*', '[', ']', ',', '!'], ' ', $address);
        $address = preg_replace('/\s\s+/', ' ', $address);

        preg_match('/^([0-9]*)(.*?)([0-9]+)(.*)/', $address, $matches);

        if (!empty($matches[2])) {
            $result['street'] = trim($matches[1] . $matches[2]);
            $result['house_number'] = trim($matches[3]);
            $result['number_addition'] = trim($matches[4]);
        } else {
            $result['street'] = $address;
        }

        return $result;
    }
}

function checkoutautoload($payment_method)
{
    $class_name = Tools::strtolower($payment_method);
    $path = dirname(__FILE__) . "/{$class_name}.php";
    if (file_exists($path)) {
        require_once $path;
    } else {
        exit('Class not found!');
    }
}
