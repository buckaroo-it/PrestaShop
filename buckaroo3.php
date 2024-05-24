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
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'buckaroo3/vendor/autoload.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/api/paymentmethods/responsefactory.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/common.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/library/logger.php';

use Buckaroo\BuckarooClient;
use Buckaroo\PrestaShop\Classes\Issuers\Ideal as IssuersIdeal;
use Buckaroo\PrestaShop\Classes\Issuers\PayByBank as IssuersPayByBank;
use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\Form\Modifier\ProductFormModifier;
use Buckaroo\PrestaShop\Src\Install\DatabaseTableInstaller;
use Buckaroo\PrestaShop\Src\Install\DatabaseTableUninstaller;
use Buckaroo\PrestaShop\Src\Install\IdinColumnsRemover;
use Buckaroo\PrestaShop\Src\Install\Installer;
use Buckaroo\PrestaShop\Src\Install\Uninstaller;
use Buckaroo\PrestaShop\Src\Refund\Settings as RefundSettings;
use Buckaroo\PrestaShop\Src\Repository\RawBuckarooFeeRepository;
use Buckaroo\PrestaShop\Src\Repository\RawPaymentMethodRepository;
use Buckaroo\PrestaShop\Src\Service\BuckarooIdinService;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;

class Buckaroo3 extends PaymentModule
{
    public $logger;

    public function __construct()
    {
        $this->initializeModuleInfo();
        parent::__construct();
        $this->initializeLogger();
        $this->initializeDisplayName();
        $this->checkConfiguration();
    }

    private function initializeModuleInfo()
    {
        $this->name = 'buckaroo3';
        $this->tab = 'payments_gateways';
        $this->version = '4.2.1';
        $this->author = 'Buckaroo';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->module_key = '8d2a2f65a77a8021da5d5ffccc9bbd2b';
        $this->ps_versions_compliancy = ['min' => '1', 'max' => _PS_VERSION_];
        $this->displayName = $this->l('Buckaroo Payments') . ' (v ' . $this->version . ')';
        $this->description = $this->l('Buckaroo Payment module. Compatible with PrestaShop version 1.7.x + 8.1.4');
        $this->confirmUninstall = $this->l('Are you sure you want to delete Buckaroo Payments module?');
        $this->tpl_folder = 'buckaroo3';
    }

    private function initializeLogger()
    {
        $this->logger = new \Logger(CoreLogger::INFO, '');
    }

    private function initializeDisplayName()
    {
        $response = ResponseFactory::getResponse();
        if ($response && $response->isValid()) {
            if ($response->brq_transaction_type == 'I150') {
                $this->displayName = 'Group transaction';
            } elseif ($response->hasSucceeded()) {
                $this->displayName = $response->payment_method;
            } elseif (isset($response->status) && $response->status > 0) {
                $this->displayName = (new RawPaymentMethodRepository())->getPaymentMethodsLabel($response->payment_method);
            } else {
                $this->displayName = $this->l('Buckaroo Payments (v 4.2.1)');
            }
        }
    }

    private function checkConfiguration()
    {
        if (!Configuration::get('BUCKAROO_MERCHANT_KEY')
            || !Configuration::get('BUCKAROO_SECRET_KEY')
            || !Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT')
            || !Configuration::get('BUCKAROO_ORDER_STATE_SUCCESS')
            || !Configuration::get('BUCKAROO_ORDER_STATE_FAILED')) {
            return '';
        }
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function hookDisplayAdminOrderMainBottom($params)
    {
        $order = new Order($params['id_order']);

        if ($order->module === 'buckaroo3') {
            $refundProvider = $this->get('buckaroo.refund.admin.provider');

            $this->smarty->assign($refundProvider->get($order));

            return $this->display(__FILE__, 'views/templates/hook/refund-hook.tpl');
        }
    }

    /**
     * @throws LocalizationException
     */
    public function hookDisplayOrderConfirmation(array $params)
    {
        $order = isset($params['objOrder']) ? $params['objOrder'] : null;
        $order = isset($params['order']) ? $params['order'] : $order;

        if (!$order || !($cart = new Cart($order->id_cart))) {
            return '';
        }

        $buckarooFeeData = (new RawBuckarooFeeRepository())->getFeeByOrderId($order->id);

        if (!$buckarooFeeData) {
            return '';
        }

        $buckarooFee = (float) $buckarooFeeData['buckaroo_fee_tax_excl'];
        $taxData = $this->calculateTax($cart, $buckarooFee);
        $paymentFeeLabel = Configuration::get('PAYMENT_FEE_FRONTEND_LABEL');

        // Assign data to Smarty
        $this->context->smarty->assign([
            'orderBuckarooFee' => $this->formatPrice($taxData['feeInclTax']),
            'paymentFeeLabel' => $paymentFeeLabel,
        ]);

        // Fetch and return the template content
        return $this->display(__FILE__, 'views/templates/hook/order-confirmation-fee.tpl');
    }

    private function calculateTax($cart, $fee)
    {
        $address = new Address($cart->id_address_invoice);
        $taxManager = TaxManagerFactory::getManager($address, (int) Configuration::get('PS_TAX'));
        $taxCalculator = $taxManager->getTaxCalculator();
        $taxRate = $taxCalculator->getTotalRate();
        $taxAmount = $fee * ($taxRate / 100);
        $feeInclTax = $fee + $taxAmount;

        return [
            'taxRate' => $taxRate,
            'taxAmount' => $taxAmount,
            'feeInclTax' => $feeInclTax,
        ];
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        if (!parent::install()) {
            $this->_errors[] = $this->l('Unable to install module');
            return false;
        }

        if (!$this->runInstallers()) {
            return false;
        }

        (new RefundSettings())->install();
        $this->configureOrderStates();
        Configuration::updateValue('PS_COOKIE_SAMESITE', 'None');

        return true;
    }

    private function runInstallers()
    {
        $databaseTableInstaller = new DatabaseTableInstaller();
        $coreInstaller = new Installer($this, $databaseTableInstaller);

        if (!$coreInstaller->install()) {
            $this->_errors = array_merge($this->_errors, $coreInstaller->getErrors());
            return false;
        }

        return true;
    }

    private function configureOrderStates()
    {
        $states = OrderState::getOrderStates((int)Configuration::get('PS_LANG_DEFAULT'));
        $currentStates = [];

        foreach ($states as $state) {
            $state = (object)$state;
            $currentStates[$state->id_order_state] = $state->name;
        }

        $defaultOrderState = $this->getOrCreateDefaultOrderState($currentStates);
        Configuration::updateValue('BUCKAROO_ORDER_STATE_DEFAULT', $defaultOrderState->id);
        Configuration::updateValue('BUCKAROO_ORDER_STATE_SUCCESS', Configuration::get('PS_OS_PAYMENT'));
        Configuration::updateValue('BUCKAROO_ORDER_STATE_FAILED', Configuration::get('PS_OS_CANCELED'));
    }

    private function getOrCreateDefaultOrderState($currentStates)
    {
        if (($state_id = array_search($this->l('Awaiting for Remote payment'), $currentStates)) === false) {
            return $this->createDefaultOrderState();
        } else {
            $defaultOrderState = new stdClass();
            $defaultOrderState->id = $state_id;
            return $defaultOrderState;
        }
    }

    private function createDefaultOrderState()
    {
        $defaultOrderState = new OrderState();
        $defaultOrderState->name = [Configuration::get('PS_LANG_DEFAULT') => $this->l('Awaiting for Remote payment')];
        $defaultOrderState->module_name = $this->name;
        $defaultOrderState->send_email = 0;
        $defaultOrderState->template = '';
        $defaultOrderState->invoice = 0;
        $defaultOrderState->color = '#FFF000';
        $defaultOrderState->unremovable = false;
        $defaultOrderState->logable = 0;

        if ($defaultOrderState->add()) {
            $this->copyLogo($defaultOrderState->id);
        }

        return $defaultOrderState;
    }

    private function copyLogo($stateId)
    {
        $source = dirname(__FILE__) . '/logo.gif';
        $destination = dirname(__FILE__) . '/../../img/os/' . (int)$stateId . '.gif';
        if (!file_exists($destination)) {
            copy($source, $destination);
        }
    }

    public function uninstall()
    {
        if (!$this->runUninstallers()) {
            return false;
        }

        try {
            $refundSettingsService = $this->get('buckaroo.refund.settings');
            if ($refundSettingsService) {
                $refundSettingsService->uninstall();
            }
        } catch (\Exception $e) {
            $this->_errors[] = 'Failed to uninstall buckaroo.refund.settings: ' . $e->getMessage();
        }

        return parent::uninstall();
    }

    private function runUninstallers()
    {
        $databaseTableUninstaller = new DatabaseTableUninstaller();
        $databaseIdinColumnsRemover = new IdinColumnsRemover();
        $uninstall = new Uninstaller($this, $databaseTableUninstaller, $databaseIdinColumnsRemover);

        if (!$uninstall->uninstall()) {
            $this->_errors[] = $uninstall->getErrors();
            return false;
        }

        return true;
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('controller') == 'AdminModules' && Tools::getValue('configure') == 'buckaroo3') {
            $this->context->controller->addCSS($this->_path . 'views/css/buckaroo3.vue.css', 'all');
        }
        $this->context->controller->addCSS($this->_path . 'views/css/buckaroo3.admin.css', 'all');
    }

    public function getContent()
    {
        $tokenManager = $this->get('security.csrf.token_manager');
        $userProvider = $this->get('prestashop.user_provider');
        $token = $tokenManager->getToken($userProvider->getUsername())->getValue();

        $this->context->smarty->assign([
            'pathApp' => $this->_path . 'views/js/buckaroo.vue.js',
            'baseUrl' => $this->context->shop->getBaseURL(true),
            'adminUrl' => explode('?', $this->context->link->getAdminLink(AdminDashboard::class))[0],
            'token' => $token,
        ]);

        return $this->context->smarty->fetch('module:buckaroo3/views/templates/admin/app.tpl');
    }

    private function isActivated()
    {
        $websiteKey = Configuration::get('BUCKAROO_MERCHANT_KEY');
        $secretKey = Configuration::get('BUCKAROO_SECRET_KEY');

        return $this->active && $this->checkKeys($websiteKey, $secretKey);
    }

    private function checkKeys($websiteKey, $secretKey): bool
    {
        if (empty($websiteKey) || empty($secretKey)) {
            return false;
        }
        $buckarooClient = new BuckarooClient($websiteKey, $secretKey);

        return $buckarooClient->confirmCredential();
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->isActivated()) {
            return [];
        }

        $cookie = new Cookie('ps');
        $cart = new Cart($params['cookie']->__get('id_cart'));
        $customer = new Customer($cart->id_customer);
        $cookie_id_lang = (int)$cookie->id_lang;
        $id_lang = $cookie_id_lang ? $cookie_id_lang : (int)(Configuration::get('PS_LANG_DEFAULT'));
        $addresses = $customer->getAddresses($id_lang);
        $company = '';
        $vat = '';
        $firstNameBilling = '';
        $firstNameShipping = '';
        $lastNameBilling = '';
        $lastNameShipping = '';
        $phone = '';
        $phone_mobile = '';

        foreach ($addresses as $address) {
            if ($address['id_address'] == $cart->id_address_delivery) {
                $phone = $address['phone'];
                $phone_mobile = $address['phone_mobile'];
                $firstNameShipping = $address['firstname'];
                $lastNameShipping = $address['lastname'];
            }
            if ($address['id_address'] == $cart->id_address_invoice) {
                $company = $address['company'];
                $vat = $address['vat_number'];
                $phone_billing = $address['phone'];
                $phone_mobile_billing = $address['phone_mobile'];
                $firstNameBilling = $address['firstname'];
                $lastNameBilling = $address['lastname'];
            }
        }
        $phone_afterpay_shipping = '';
        if (!empty($phone_mobile)) {
            $phone_afterpay_shipping = $phone_mobile;
        }
        if (empty($phone_afterpay_shipping) && !empty($phone)) {
            $phone_afterpay_shipping = $phone;
        }

        $phone_afterpay_billing = '';

        if (!empty($phone_mobile_billing)) {
            $phone_afterpay_billing = $phone_mobile_billing;
        } elseif (!empty($phone_billing)) {
            $phone_afterpay_billing = $phone_billing;
        }

        $address_differ = 0;

        if ($cart->id_address_delivery != $cart->id_address_invoice) {
            if ($lastNameShipping == $lastNameBilling
                && $firstNameShipping == $firstNameBilling) {
                $address_differ = 2;
            } else {
                $address_differ = 1;
            }
        }

        $buckarooConfigService = $this->getBuckarooConfigService();

        $buckarooPaymentService = $this->get('buckaroo.config.api.payment.service');

        try {
            $this->context->smarty->assign(
                [
                    'address_differ' => $address_differ,
                    'this_path' => $this->_path,
                    'customer_gender' => $customer->id_gender,
                    'customer_name' => $customer->firstname . ' ' . $customer->lastname,
                    'customer_email' => $customer->email,
                    'customer_birthday' => explode('-', $customer->birthday),
                    'customer_company' => $company,
                    'customer_vat' => $vat,
                    'phone' => $phone,
                    'phone_mobile' => $phone_mobile,
                    'phone_afterpay_shipping' => $phone_afterpay_shipping,
                    'phone_afterpay_billing' => $phone_afterpay_billing,
                    'total' => $cart->getOrderTotal(true, 3),
                    'country' => Country::getIsoById(Tools::getCountry()),
                    'afterpay_show_coc' => $buckarooPaymentService->showAfterpayCoc($cart),
                    'billink_show_coc' => $buckarooPaymentService->showBillinkCoc($cart),
                    'idealIssuers' => (new IssuersIdeal())->get(),
                    'idealDisplayMode' => $buckarooConfigService->getConfigValue('ideal', 'display_type'),
                    'paybybankIssuers' => (new IssuersPayByBank())->get(),
                    'payByBankDisplayMode' => $buckarooConfigService->getConfigValue('paybybank', 'display_type'),
                    'methodsWithFinancialWarning' => $buckarooPaymentService->paymentMethodsWithFinancialWarning(),
                    'creditcardIssuers' => $buckarooConfigService->getActiveCreditCards(),
                    'creditCardDisplayMode' => $buckarooConfigService->getConfigValue('creditcard', 'display_type'),
                    'in3Method' => $this->get('buckaroo.classes.issuers.capayableIn3')->getMethod(),
                    'showIdealIssuers' => $buckarooConfigService->getConfigValue('ideal', 'show_issuers') ?? true,
                    'buckaroo_idin_test' => $buckarooConfigService->getConfigValue('idin', 'mode'),
                    'houseNumbersAreValid' => $buckarooPaymentService->areHouseNumberValidForCountryDE($cart)
                ]
            );
        } catch (Exception $e) {
            $this->logger->logError('Buckaroo3::hookPaymentOptions - ' . $e->getMessage());
        }

        return $buckarooPaymentService->getPaymentOptions($cart);
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     * @throws LocalizationException
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        if (Tools::getValue('response_received') || (Tools::getValue('id_order') && Tools::getValue('success'))) {
            $order = new Order(Tools::getValue('id_order'));
            $price = $this->formatPrice($order->getOrdersTotalPaid());
            $isGuest = $this->context->customer->is_guest || !$this->context->customer->id;

            if (Tools::getValue('response_received') == 'transfer') {
                $this->context->smarty->assign([
                    'is_guest' => $isGuest,
                    'order' => $order,
                    'price' => $price,
                    'message' => $this->context->cookie->HtmlText,
                ]);

                return $this->display(__FILE__, 'payment_return_redirectsuccess.tpl');
            }
            $this->context->smarty->assign([
                'is_guest' => $isGuest,
                'order' => $order,
                'price' => $price,
            ]);

            return $this->display(__FILE__, 'payment_return_success.tpl');
        }
        Tools::redirect('index.php?fc=module&module=buckaroo3&controller=error');
        exit;
    }

    public function hookDisplayHeader()
    {
        Media::addJsDef([
            'buckarooAjaxUrl' => $this->context->link->getModuleLink('buckaroo3', 'ajax'),
            'buckarooFees' => $this->getBuckarooFeeService()->getBuckarooFees(),
            'paymentFeeLabel' => Configuration::get('PAYMENT_FEE_FRONTEND_LABEL'),
            'buckarooMessages' => [
                'validation' => [
                    'date' => $this->l('Please enter correct birthdate date'),
                    'required' => $this->l('Field is required'),
                    'bank' => $this->l('Please select your bank'),
                    'agreement' => $this->l('Please accept licence agreements'),
                    'iban' => $this->l('A valid IBAN is required'),
                    'age' => $this->l('You must be at least 18 years old'),
                ],
            ],
        ]);

        $this->context->controller->addCSS($this->_path . 'views/css/buckaroo3.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/buckaroo.js', 'all');
    }

    public static function resolveStatusCode($status_code, $id_order = null)
    {
        switch ($status_code) {
            case BuckarooAbstract::BUCKAROO_SUCCESS:
                return self::isOrderBackOrder($id_order) ?
                    Configuration::get('PS_OS_OUTOFSTOCK_PAID') :
                    (Configuration::get('BUCKAROO_ORDER_STATE_SUCCESS') ?: Configuration::get('PS_OS_PAYMENT'));
            case BuckarooAbstract::BUCKAROO_PENDING_PAYMENT:
                return Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');
            case BuckarooAbstract::BUCKAROO_CANCELED:
            case BuckarooAbstract::BUCKAROO_ERROR:
            case BuckarooAbstract::BUCKAROO_FAILED:
            case BuckarooAbstract::BUCKAROO_INCORRECT_PAYMENT:
                return Configuration::get('BUCKAROO_ORDER_STATE_FAILED') ?
                    Configuration::get('BUCKAROO_ORDER_STATE_FAILED') : Configuration::get('PS_OS_CANCELED');
            default:
                return Configuration::get('PS_OS_ERROR');
        }
    }

    private static function isOrderBackOrder($orderId)
    {
        if (!Configuration::get('PS_STOCK_MANAGEMENT')) {
            return false; // If stock management is disabled, no order is a backorder
        }

        $order = new Order($orderId);
        $orderDetails = $order->getOrderDetailList();

        foreach ($orderDetails as $detail) {
            $orderDetail = new OrderDetail($detail['id_order_detail']);

            // If any product is in stock, the order is not a backorder
            if ($orderDetail->product_quantity_in_stock < 0) {
                return true;
            }
        }

        // If all products are out of stock, the order is a backorder
        return false;
    }

    public function getBuckarooFee($payment_method)
    {
        $buckarooFee = $this->getBuckarooFeeService()->getBuckarooFeeValue($payment_method);

        if (!$buckarooFee) {
            return null;
        }

        // Remove any whitespace from the fee.
        $buckarooFee = trim($buckarooFee);

        if (strpos($buckarooFee, '%') !== false) {
            $buckarooFee = str_replace('%', '', $buckarooFee);
            $buckarooFee = (float)$this->payment_request->amountDebit * ((float)$buckarooFee / 100);
        } else {
            $buckarooFee = (float)$buckarooFee;
        }

        $taxRate = $this->context->cart->getAverageProductsTaxRate();
        $buckarooFeeTax = $buckarooFee * $taxRate;
        $buckarooFeeTaxIncl = $buckarooFee + $buckarooFeeTax;

        return [
            'buckaroo_fee_tax_excl' => $buckarooFee,
            'buckaroo_fee_tax' => $buckarooFeeTax,
            'buckaroo_fee_tax_incl' => $buckarooFeeTaxIncl,
        ];
    }

    public function hookActionEmailSendBefore($params)
    {
        if (!isset($params['cart']->id)) {
            return true;
        }

        $cart = new Cart($params['cart']->id);
        $orderId = Order::getOrderByCartId($cart->id);
        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return true;
        }

        if ($params['template'] == 'order_conf') {
            $params['templatePath'] = _PS_MODULE_DIR_ . 'buckaroo3/mails/';
        }

        $templatesToModify = ['order_conf'];
        if (in_array($params['template'], $templatesToModify)) {
            $paymentMethodLabel = $order->payment;
            $buckarooFeeService = $this->getBuckarooFeeService();
            $paymentMethodName = $buckarooFeeService->getPaymentMethodByLabel($paymentMethodLabel);

            $buckarooFee = $this->getBuckarooFee($paymentMethodName);

            // Ensure buckarooFee is an array
            if (!is_array($buckarooFee)) {
                $buckarooFee = [
                    'buckaroo_fee_tax_excl' => 0,
                    'buckaroo_fee_tax' => 0,
                    'buckaroo_fee_tax_incl' => 0,
                ];
            }

            $buckarooFeeTaxExcl = $buckarooFee['buckaroo_fee_tax_excl'];
            $buckarooFeeTaxIncl = $buckarooFee['buckaroo_fee_tax_incl'];

            $paymentFeeLabel = Configuration::get('PAYMENT_FEE_FRONTEND_LABEL');

            $params['templateVars']['{payment_fee_label}'] = $paymentFeeLabel;

            if ($buckarooFeeTaxIncl > 0) {
                $params['templateVars']['{payment_fee}'] = Tools::displayPrice($buckarooFeeTaxExcl);
                $params['templateVars']['{payment_fee_tax}'] = Tools::displayPrice($buckarooFee['buckaroo_fee_tax']);
                $params['templateVars']['{total_paid}'] = Tools::displayPrice($order->total_paid + $buckarooFeeTaxIncl);
                // Include the total tax paid, which includes the payment fee tax
                $totalTaxPaid = $order->total_paid_tax_incl - $order->total_paid_tax_excl + $buckarooFee['buckaroo_fee_tax'];
                $params['templateVars']['{total_tax_paid}'] = Tools::displayPrice($totalTaxPaid);
            } else {
                $params['templateVars']['{payment_fee}'] = Tools::displayPrice(0);
            }
        }

        return true;
    }


    public function hookDisplayPDFInvoice($params)
    {
        if ($params['object'] instanceof OrderInvoice) {
            $order = $params['object']->getOrder();
            $buckarooFeeData = (new RawBuckarooFeeRepository())->getFeeByOrderId($order->id);

            if (!$buckarooFeeData) {
                return;
            }

            $buckarooFee = (float) $buckarooFeeData['buckaroo_fee_tax_excl'];
            $taxData = $this->calculateTax($order, $buckarooFee);

            $this->context->smarty->assign([
                'payment_fee_label' => Configuration::get('PAYMENT_FEE_FRONTEND_LABEL'),
                'order_buckaroo_fee' => $this->formatPrice($taxData['feeInclTax']),
            ]);

            return $this->context->smarty->fetch($this->getLocalPath() . 'views/templates/admin/invoice_fee.tpl');
        }
    }

    public function isPaymentModeActive($method)
    {
        $isLive = (int)\Configuration::get(Config::BUCKAROO_TEST);
        $configArray = $this->getBuckarooConfigService()->getConfigArrayForMethod($method);

        if (!empty($configArray) && isset($configArray['mode'])) {
            if ($isLive === 0) {
                return $configArray['mode'] === 'test';
            } elseif ($isLive === 1) {
                return $configArray['mode'] === 'live';
            }
        }

        return false;
    }

    public function isIdinProductBoxShow($params)
    {
        if (!$this->isPaymentModeActive('idin')) {
            return false;
        }

        switch ($this->getBuckarooConfigService()->getConfigValue('idin', 'display_mode')) {
            case 'product':
                return $this->isProductBuckarooIdinEnabled($params['product']->id);
            case 'global':
                return true;
            default:
                return false;
        }
    }

    private function isProductBuckarooIdinEnabled(int $productId)
    {
        $sql = new DbQuery();

        $sql->select('buckaroo_idin');
        $sql->from('bk_product_idin');
        $sql->where('product_id = ' . pSQL($productId));

        $buckarooIdin = Db::getInstance()->getValue($sql);

        return $buckarooIdin == 1;
    }

    public function isIdinCheckout($cart)
    {
        if (!$this->isPaymentModeActive('idin')) {
            return false;
        }

        switch ($this->getBuckarooConfigService()->getConfigValue('idin', 'display_mode')) {
            case 'product':
                foreach ($cart->getProducts(true) as $value) {
                    return $this->isProductBuckarooIdinEnabled($value['id_product']);
                }
                break;
            case 'global':
                return true;
            default:
                return false;
        }

        return false;
    }

    public function getBuckarooConfigService()
    {
        return $this->get('buckaroo.config.api.config.service');
    }

    public function getBuckarooFeeService()
    {
        return $this->get('buckaroo.config.api.fee.service');
    }

    public function hookDisplayProductExtraContent($params)
    {
        if ($this->isIdinProductBoxShow($params)) {
            $this->smarty->assign([
                'this_path' => _MODULE_DIR_ . $this->tpl_folder . '/',
            ]);

            $content = $this->display(__FILE__, 'views/templates/hook/idin_box.tpl');
            $productExtraContent = new PrestaShop\PrestaShop\Core\Product\ProductExtraContent();
            $productExtraContent->setTitle($this->l('iDIN Info'));
            $productExtraContent->setContent($content);

            return [$productExtraContent];
        }
    }

    /**
     * Modify product form builder
     *
     * @param array $params
     *
     * @throws Exception
     */
    public function hookActionProductFormBuilderModifier(array $params): void
    {
        /** @var ProductFormModifier $productFormModifier */
        $productFormModifier = $this->get(ProductFormModifier::class);
        $productId = (int)$params['id'];

        $productFormModifier->modify($productId, $params['form_builder']);
    }

    public function hookActionAfterUpdateProductFormHandler(array $params)
    {
        $this->updateProductFormHandler($params);
    }

    private function updateProductFormHandler(array $params)
    {
        $productId = $params['form_data']['id'];
        $buckarooIdin = $params['form_data']['buckaroo_idin']['buckaroo_idin'];

        $buckarooIdinService = new BuckarooIdinService();

        try {
            if ($buckarooIdinService->checkProductIdExists($productId)) {
                $buckarooIdinService->updateProductData($productId, $buckarooIdin);
            } else {
                $buckarooIdinService->insertProductData($productId, $buckarooIdin);
            }
        } catch (Exception $e) {
            $this->logger->logError('Buckaroo3::updateCustomerReviewStatus - ' . $e->getMessage());
        }
    }

    /**
     * @throws LocalizationException
     */
    private function formatPrice($amount): string
    {
        $currency = \Context::getContext()->currency;

        return \Tools::getContextLocale($this->context)->formatPrice($amount, $currency->iso_code);
    }
}
