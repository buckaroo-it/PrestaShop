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

use Buckaroo\PrestaShop\Src\AddressComponents;
use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;
use Buckaroo\PrestaShop\Src\Service\BuckarooFeeService;
use PrestaShop\Decimal\DecimalNumber;

include_once _PS_MODULE_DIR_ . 'buckaroo3/api/paymentmethods/paymentrequestfactory.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class Checkout
{
    protected $customVars = [];

    public const CHECKOUT_TYPE_PAYPAL = 'paypal';
    public const CHECKOUT_TYPE_IDEAL = 'ideal';
    public const CHECKOUT_TYPE_PAYBYBANK = 'paybybank';
    public const CHECKOUT_TYPE_SEPADIRECTDEBIT = 'sepadirectdebit';
    public const CHECKOUT_TYPE_GIROPAY = 'giropay';
    public const CHECKOUT_TYPE_KBCPAYMENTBUTTON = 'kbcpaymentbutton';
    public const CHECKOUT_TYPE_BANCONTACTMRCASH = 'bancontactmrcash';
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
    public const CHECKOUT_TYPE_TRUSTLY = 'trustly';
    public const CHECKOUT_TYPE_WECHATPAY = 'wechatpay';
    public const CHECKOUT_TYPE_ALIPAY = 'alipay';
    public const CHECKOUT_TYPE_MBWAY = 'mbway';
    public const CHECKOUT_TYPE_MULTIBANCO = 'multibanco';
    public const CHECKOUT_TYPE_KNAKEN = 'knaken';

    // Request types (Payment Methods).
    public static $payment_method_type = [
        Checkout::CHECKOUT_TYPE_PAYPAL => 'PayPal',
        Checkout::CHECKOUT_TYPE_IDEAL => 'IDeal',
        Checkout::CHECKOUT_TYPE_PAYBYBANK => 'PayByBank',
        Checkout::CHECKOUT_TYPE_SEPADIRECTDEBIT => 'SepaDirectdebit',
        Checkout::CHECKOUT_TYPE_GIROPAY => 'Giropay',
        Checkout::CHECKOUT_TYPE_KBCPAYMENTBUTTON => 'Kbcpaymentbutton',
        Checkout::CHECKOUT_TYPE_BANCONTACTMRCASH => 'Bancontactmrcash',
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
        Checkout::CHECKOUT_TYPE_TRUSTLY => 'Trustly',
        Checkout::CHECKOUT_TYPE_WECHATPAY => 'Wechatpay',
        Checkout::CHECKOUT_TYPE_ALIPAY => 'Alipay',
        Checkout::CHECKOUT_TYPE_MBWAY => 'Mbway',
        Checkout::CHECKOUT_TYPE_MULTIBANCO => 'Multibanco',
        Checkout::CHECKOUT_TYPE_KNAKEN => 'Knaken',
    ];

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
    public $platformName;
    public $platformVersion;
    public $moduleSupplier;
    public $moduleName;
    public $moduleVersion;
    public $returnUrl;
    public $pushUrl;

    /** @var Buckaroo3 */
    public $module;

    /**
     * @var BuckarooConfigService
     */
    protected $buckarooConfigService;

    /**
     * @var BuckarooFeeService
     */
    protected $buckarooFeeService;

    /**
     * @var Context
     */
    protected $context;
    public function __construct($cart, $context)
    {
        $this->context = $context;
        $this->initialize();
        $this->module = \Module::getInstanceByName('buckaroo3');
        $this->cart = $cart;
        $this->customer = new Customer($cart->id_customer);
        $this->invoice_address = new Address((int) $cart->id_address_invoice);
        $this->shipping_address = $cart->id_address_invoice != $cart->id_address_delivery ?
            new Address((int) $cart->id_address_delivery) : $this->invoice_address;
        $this->products = $this->cart->getProducts();
        $this->buckarooConfigService = $this->module->getBuckarooConfigService();
        $this->buckarooFeeService = $this->module->getBuckarooFeeService();
    }

    abstract protected function initialize();

    protected function setCheckout()
    {
        $currency = new Currency((int) $this->cart->id_currency);
        $this->payment_request->amountDebit =
            (string) ((float) $this->cart->getOrderTotal(true, Cart::BOTH));

        $buckarooFee = $this->getBuckarooFee();

        if ($buckarooFee > 0) {
            $this->updateOrderFee($buckarooFee);
        }
        $this->payment_request->setDescription($this->cart->id);
        $this->payment_request->currency = $currency->iso_code;
        $reference = $this->reference . '_' . $this->cart->id;
        $this->payment_request->invoiceId = $reference;
        $this->payment_request->orderId = $reference;
        $this->payment_request->platformName = $this->platformName;
        $this->payment_request->platformVersion = $this->platformVersion;
        $this->payment_request->moduleSupplier = $this->moduleSupplier;
        $this->payment_request->moduleName = $this->moduleName;
        $this->payment_request->moduleVersion = $this->moduleVersion;
        $this->payment_request->returnUrl = $this->returnUrl;
        $this->payment_request->pushUrl = $this->pushUrl;
    }

    public function getBuckarooFee()
    {
        $payment_method = Tools::getValue('method');
        if ($buckarooFee = $this->buckarooFeeService->getBuckarooFeeValue($payment_method)) {
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

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Exception
     */
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

        $orderFeeNumber = new DecimalNumber((string) 0);
        $originalAmount = (float) $this->cart->getOrderTotal(true, Cart::BOTH);
        $totalPrice = new DecimalNumber((string) ($originalAmount + $buckarooFee));
        $orderFeeNumber->plus($totalPrice);

        $orderid = Order::getIdByCartId($this->cart->id);

        $order = new Order($orderid);

        $order->total_paid_tax_excl = $orderFeeNumber->plus(new DecimalNumber((string) $order->total_paid_tax_excl));
        $order->total_paid_tax_incl = $orderFeeNumber->plus(new DecimalNumber((string) $order->total_paid_tax_incl));
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
     * @param $payment_method
     * @param $cart
     *
     * @return Address subclass
     *
     * @throws Exception
     */
    final public static function getInstance($payment_method, $cart, $context)
    {
        $class_name = self::$payment_method_type[$payment_method] . 'Checkout';

        checkoutautoload($class_name); // Try to find class in api directory

        if (!class_exists($class_name)) {
            throw new Exception('Payment method not found', '1'); // TODO: ExceptionPayment
        }

        return new $class_name($cart, $context);
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
       return AddressComponents::getAddressComponents($address);
    }

    /**
     * Check if company exists
     *
     * @param mixed $company
     *
     * @return bool
     */
    protected function companyExists($company)
    {
        if (!is_string($company)) {
            return false;
        }

        return strlen(trim($company)) !== 0;
    }

    public function getPhone($address)
    {
        $phone = '';
        if (!empty($address->phone_mobile)) {
            $phone = $address->phone_mobile;
        }
        if (empty($phone) && !empty($address->phone)) {
            $phone = $address->phone;
        }

        return $phone;
    }

    public function getArticles()
    {
        $products = $this->prepareProductArticles();


        $additionalArticles = [
            $this->prepareWrappingArticle(),
            $this->prepareBuckarooFeeArticle(),
            $this->prepareShippingCostArticle(),
        ];

        foreach ($additionalArticles as $article) {
            if (!empty($article)) {
                $products[] = $article;
            }
        }

        return $this->mergeProductsBySKU($products);
    }

    protected function prepareWrappingArticle()
    {
        $wrappingCostInclTax = $this->cart->getOrderTotal(true, CartCore::ONLY_WRAPPING);

        // Get the Tax Rule Group for Wrapping
        $wrappingTaxRulesGroupId = (int)Configuration::get('PS_GIFT_WRAPPING_TAX_RULES_GROUP');
        // Get the VAT Rate for the Tax Rule Group
        $address = new Address($this->cart->id_address_delivery);
        $tax_manager = TaxManagerFactory::getManager($address, $wrappingTaxRulesGroupId);
        $tax_calculator = $tax_manager->getTaxCalculator();
        $wrappingVatRate = $tax_calculator->getTotalRate();

        return $wrappingCostInclTax > 0 ? [
            'identifier' => '0',
            'quantity' => '1',
            'price' => $wrappingCostInclTax,
            'vatPercentage' => $wrappingVatRate,
            'description' => 'Wrapping',
        ] : [];
    }

    protected function prepareBuckarooFeeArticle()
    {
        $buckarooFee = $this->getBuckarooFee();

        return $buckarooFee > 0 ? [
            'identifier' => '0',
            'quantity' => '1',
            'price' => round($buckarooFee, 2),
            'vatPercentage' => '0',
            'description' => 'buckaroo_fee',
        ] : [];
    }



    protected function prepareProductArticles()
    {
        $articles = [];
        foreach ($this->products as $item) {
            $tmp = [];
            $tmp['identifier'] = $item['id_product'];
            $tmp['quantity'] = $item['quantity'];
            $tmp['price'] = round($item['price_wt'], 2);
            $tmp['vatPercentage'] = $item['rate'];
            $tmp['description'] = $item['name'];
            $productImg =  $this->getProductImgUrl($item);
            if (is_string($productImg) && strlen($productImg)) {
                $tmp['imageUrl'] = $productImg;
            }
            $articles[] = $tmp;
        }

        return $articles;
    }

    /**
     *
     * @param Product $product
     *
     * @return string|null
     */
    private function getProductImgUrl($product)
    {
        if (!Tools::getValue('method') === "afterpay") {
            return null;
        }
        $cover = Product::getCover($product['id_product']);

        $imageTypes = ImageType::getImagesTypes("products", true);
        foreach ($imageTypes as $imageType) {
            if (
                isset($imageType['height']) &&
                isset($imageType['width']) &&
                $imageType['height'] <= 1280 &&
                $imageType['height'] >= 100 &&
                $imageType['height'] <= 1280 &&
                $imageType['height'] >= 100
            ) {
                return $this->context->link->getImageLink($product['link_rewrite'], $cover['id_image'], $imageType['name']);
            }
        }
        return null;
    }

    protected function mergeProductsBySKU($products)
    {
        $mergeProducts = [];
        foreach ($products as $item) {
            if (!isset($mergeProducts[$item['identifier']])) {
                $mergeProducts[$item['identifier']] = $item;
            } else {
                ++$mergeProducts[$item['identifier']]['quantity'];
            }
        }

        return $mergeProducts;
    }

    protected function prepareShippingCostArticle()
    {
        $shippingCost = round($this->cart->getOrderTotal(true, CartCore::ONLY_SHIPPING), 2);
        if ($shippingCost <= 0) {
            return null;
        }

        $carrier = new Carrier((int) $this->cart->id_carrier, Configuration::get('PS_LANG_DEFAULT'));

        $shippingCostsTax = (version_compare(_PS_VERSION_, '1.7.6.0', '<='))
            ? $carrier->getTaxesRate(Address::initialize())
            : $carrier->getTaxesRate();

        return [
            'identifier' => 'shipping',
            'description' => 'Shipping Costs',
            'vatPercentage' => $shippingCostsTax,
            'quantity' => 1,
            'price' => $shippingCost,
        ];
    }

    public function initials($str)
    {
        $ret = '';
        foreach (explode(' ', $str) as $word) {
            $ret .= Tools::strtoupper($word[0]) . '.';
        }

        return $ret;
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
