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
use Buckaroo\PrestaShop\Src\Repository\RawBuckarooFeeRepository;
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

    public static $payment_method_type = [
        self::CHECKOUT_TYPE_PAYPAL => 'PayPal',
        self::CHECKOUT_TYPE_IDEAL => 'IDeal',
        self::CHECKOUT_TYPE_PAYBYBANK => 'PayByBank',
        self::CHECKOUT_TYPE_SEPADIRECTDEBIT => 'SepaDirectdebit',
        self::CHECKOUT_TYPE_GIROPAY => 'Giropay',
        self::CHECKOUT_TYPE_KBCPAYMENTBUTTON => 'Kbcpaymentbutton',
        self::CHECKOUT_TYPE_BANCONTACTMRCASH => 'Bancontactmrcash',
        self::CHECKOUT_TYPE_GIFTCARD => 'GiftCard',
        self::CHECKOUT_TYPE_CREDITCARD => 'CreditCard',
        self::CHECKOUT_TYPE_SOFORTBANKING => 'Sofortbanking',
        self::CHECKOUT_TYPE_TRANSFER => 'Transfer',
        self::CHECKOUT_TYPE_AFTERPAY => 'AfterPay',
        self::CHECKOUT_TYPE_KLARNA => 'Klarna',
        self::CHECKOUT_TYPE_APPLEPAY => 'ApplePay',
        self::CHECKOUT_TYPE_BELFIUS => 'Belfius',
        self::CHECKOUT_TYPE_IDIN => 'Idin',
        self::CHECKOUT_TYPE_IN3 => 'In3',
        self::CHECKOUT_TYPE_IN3Old => 'In3Old',
        self::CHECKOUT_TYPE_BILLINK => 'Billink',
        self::CHECKOUT_TYPE_EPS => 'Eps',
        self::CHECKOUT_TYPE_PAYCONIQ => 'Payconiq',
        self::CHECKOUT_TYPE_PAYPEREMAIL => 'PayPerEmail',
        self::CHECKOUT_TYPE_PRZELEWY24 => 'Przelewy24',
        self::CHECKOUT_TYPE_TRUSTLY => 'Trustly',
        self::CHECKOUT_TYPE_WECHATPAY => 'Wechatpay',
        self::CHECKOUT_TYPE_ALIPAY => 'Alipay',
        self::CHECKOUT_TYPE_MBWAY => 'Mbway',
        self::CHECKOUT_TYPE_MULTIBANCO => 'Multibanco',
        self::CHECKOUT_TYPE_KNAKEN => 'Knaken',
    ];

    protected $payment_request;
    protected $payment_response;
    protected $cart;
    protected $customer;
    protected $invoice_address;
    protected $shipping_address;
    protected $products;
    protected $reference;

    public $platformName;
    public $platformVersion;
    public $moduleSupplier;
    public $moduleName;
    public $moduleVersion;
    public $returnUrl;
    public $pushUrl;

    /** @var Buckaroo3 */
    public $module;

    /** @var BuckarooConfigService */
    protected $buckarooConfigService;

    /** @var BuckarooFeeService */
    protected $buckarooFeeService;

    /** @var Context */
    protected $context;

    public function __construct($cart, $context)
    {
        $this->context = $context;
        $this->initialize();
        $this->module = \Module::getInstanceByName('buckaroo3');
        $this->cart = $cart;
        $this->customer = new Customer($cart->id_customer);
        $this->invoice_address = new Address((int) $cart->id_address_invoice);
        $this->shipping_address = $cart->id_address_invoice != $cart->id_address_delivery ? new Address((int) $cart->id_address_delivery) : $this->invoice_address;
        $this->products = $this->cart->getProducts();
        $this->buckarooConfigService = $this->module->getBuckarooConfigService();
        $this->buckarooFeeService = $this->module->getBuckarooFeeService();
    }

    abstract protected function initialize();

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

    protected function setCheckout()
    {
        $currency = new Currency((int) $this->cart->id_currency);
        $this->payment_request->amountDebit = (string) ((float) $this->cart->getOrderTotal(true, Cart::BOTH));
        $buckarooFee = $this->module->getBuckarooFee(Tools::getValue('method'));

        if (is_array($buckarooFee) && $buckarooFee['buckaroo_fee_tax_incl'] > 0) {
            $this->updateOrderFee($buckarooFee);
        }

        $this->setPaymentRequestDetails($currency);
    }

    protected function setPaymentRequestDetails($currency)
    {
        $reference = $this->reference . '_' . $this->cart->id;
        $this->payment_request->setDescription($this->cart->id);
        $this->payment_request->currency = $currency->iso_code;
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

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Exception
     */
    public function updateOrderFee($buckarooFee)
    {
        if (!is_array($buckarooFee)) {
            throw new Exception('buckarooFee should be an array');
        }

        $buckarooFeeTaxExcl = $buckarooFee['buckaroo_fee_tax_excl'];
        $buckarooFeeTaxIncl = $buckarooFee['buckaroo_fee_tax_incl'];

        $currency = new Currency((int) $this->cart->id_currency);
        $order_id = Order::getIdByCartId($this->cart->id);

        \PrestaShopLogger::addLog('buckarooFeeTaxExcl: ' . $buckarooFeeTaxExcl . ', buckarooFeeTaxIncl: ' . $buckarooFeeTaxIncl, 1);

        $this->payment_request->amountDebit = (string) ((float) $this->payment_request->amountDebit + $buckarooFeeTaxIncl);

        (new RawBuckarooFeeRepository())->insertFee($this->reference, $this->cart->id, $order_id, $buckarooFeeTaxExcl, $buckarooFeeTaxIncl, $currency->iso_code);

        $this->updateOrderTotals($order_id, $buckarooFeeTaxExcl, $buckarooFeeTaxIncl);
    }

    protected function updateOrderTotals($order_id, $buckarooFeeTaxExcl, $buckarooFeeTaxIncl)
    {
        $originalAmountExcl = (float) $this->cart->getOrderTotal(false, Cart::BOTH);
        $originalAmountIncl = (float) $this->cart->getOrderTotal(true, Cart::BOTH);
        $totalPriceExcl = new DecimalNumber((string) ($originalAmountExcl + $buckarooFeeTaxExcl));
        $totalPriceIncl = new DecimalNumber((string) ($originalAmountIncl + $buckarooFeeTaxIncl));

        $order = new Order($order_id);
        $order->total_paid_tax_excl = $totalPriceExcl->toPrecision(2);
        $order->total_paid_tax_incl = $totalPriceIncl->toPrecision(2);
        $order->total_paid = $totalPriceIncl->toPrecision(2);
        $order->total_paid_real = $totalPriceIncl->toPrecision(2);
        $order->update();
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->pay();
    }

    public function isRequestSucceeded()
    {
        return !empty($this->payment_response) && $this->payment_response->hasSucceeded();
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
        return $this->payment_response->statuscode ?? 0;
    }

    public function getResponse()
    {
        return $this->payment_response ?? null;
    }

    /**
     * Given a checkout_type_id, return an instance of that subclass.
     *
     * @param string $payment_method
     * @param Cart $cart
     * @param Context $context
     *
     * @return Checkout
     *
     * @throws Exception
     */
    final public static function getInstance($payment_method, $cart, $context)
    {
        $class_name = self::$payment_method_type[$payment_method] . 'Checkout';
        checkoutautoload($class_name);

        if (!class_exists($class_name)) {
            throw new Exception('Payment method not found', '1');
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
        return is_string($company) && strlen(trim($company)) !== 0;
    }

    public function getPhone($address)
    {
        return !empty($address->phone_mobile) ? $address->phone_mobile : $address->phone ?? '';
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

        if ($wrappingCostInclTax <= 0) {
            return [];
        }

        $wrappingVatRate = $this->getTaxRate($this->cart->id_address_delivery, (int) Configuration::get('PS_GIFT_WRAPPING_TAX_RULES_GROUP'));

        return [
            'identifier' => '0',
            'quantity' => '1',
            'price' => $wrappingCostInclTax,
            'vatPercentage' => $wrappingVatRate,
            'description' => 'Wrapping',
        ];
    }

    protected function prepareBuckarooFeeArticle()
    {
        $buckarooFee = $this->module->getBuckarooFee(Tools::getValue('method'));

        if (!is_array($buckarooFee) || $buckarooFee['buckaroo_fee_tax_excl'] <= 0) {
            return [];
        }

        return [
            'identifier' => '0',
            'quantity' => '1',
            'price' => round($buckarooFee['buckaroo_fee_tax_excl'], 2),
            'vatPercentage' => '0',
            'description' => 'buckaroo_fee',
        ];
    }

    protected function prepareProductArticles()
    {
        $articles = [];
        foreach ($this->products as $item) {
            $article = [
                'identifier' => $item['id_product'],
                'quantity' => $item['quantity'],
                'price' => round($item['price_wt'], 2),
                'vatPercentage' => $item['rate'],
                'description' => $item['name'],
            ];

            $productImg = $this->getProductImgUrl($item);
            if (!empty($productImg)) {
                $article['imageUrl'] = $productImg;
            }

            $articles[] = $article;
        }

        return $articles;
    }

    /**
     * Get product image URL if method is "afterpay"
     *
     * @param array $product
     *
     * @return string|null
     */
    private function getProductImgUrl($product)
    {
        if (Tools::getValue('method') !== "afterpay") {
            return null;
        }

        $cover = Product::getCover($product['id_product']);
        $imageTypes = ImageType::getImagesTypes("products", true);

        foreach ($imageTypes as $imageType) {
            if (isset($imageType['height'], $imageType['width']) &&
                $imageType['height'] <= 1280 &&
                $imageType['height'] >= 100 &&
                $imageType['width'] <= 1280 &&
                $imageType['width'] >= 100) {
                return $this->context->link->getImageLink($product['link_rewrite'], $cover['id_image'], $imageType['name']);
            }
        }

        return null;
    }

    protected function mergeProductsBySKU($products)
    {
        $mergedProducts = [];

        foreach ($products as $item) {
            if (!isset($mergedProducts[$item['identifier']])) {
                $mergedProducts[$item['identifier']] = $item;
            } else {
                $mergedProducts[$item['identifier']]['quantity'] += $item['quantity'];
            }
        }

        return $mergedProducts;
    }

    protected function prepareShippingCostArticle()
    {
        $shippingCost = round($this->cart->getOrderTotal(true, CartCore::ONLY_SHIPPING), 2);

        if ($shippingCost <= 0) {
            return null;
        }

        $shippingCostsTax = $this->getTaxRate($this->cart->id_address_delivery, Configuration::get('PS_TAX'));

        return [
            'identifier' => 'shipping',
            'description' => 'Shipping Costs',
            'vatPercentage' => $shippingCostsTax,
            'quantity' => 1,
            'price' => $shippingCost,
        ];
    }

    /**
     * Get tax rate for a given address and tax rules group ID
     *
     * @param int $addressId
     * @param int $taxRulesGroupId
     *
     * @return float
     */
    protected function getTaxRate($addressId, $taxRulesGroupId)
    {
        $address = new Address($addressId);
        $taxManager = TaxManagerFactory::getManager($address, $taxRulesGroupId);
        $taxCalculator = $taxManager->getTaxCalculator();
        return $taxCalculator->getTotalRate();
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
