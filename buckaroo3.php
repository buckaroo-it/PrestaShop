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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class Buckaroo3 extends PaymentModule
{
    const MODULE_VERSION = '5.1.0';
    
    public $logger;

    /**
     * @var ContainerInterface|null
     */
    private $coreServiceContainer = null;

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
        $this->version = self::MODULE_VERSION;
        $this->author = 'Buckaroo';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->module_key = '8d2a2f65a77a8021da5d5ffccc9bbd2b';
        $this->ps_versions_compliancy = ['min' => '1.7.0', 'max' => _PS_VERSION_];
        $this->displayName = $this->l('Buckaroo Payments') . ' (v ' . $this->version . ')';
        $this->description = $this->l('Buckaroo Payment module. Compatible with PrestaShop version 1.7.x + 9.0.1');
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
                $this->displayName = $this->l('Buckaroo Payments (v ' . self::MODULE_VERSION . ')');
            }
        }
    }

    private function checkConfiguration(): bool
    {
        $requiredConfigs = [
            'BUCKAROO_MERCHANT_KEY',
            'BUCKAROO_SECRET_KEY',
            'BUCKAROO_ORDER_STATE_DEFAULT',
            'BUCKAROO_ORDER_STATE_SUCCESS',
            'BUCKAROO_ORDER_STATE_FAILED'
        ];
        
        foreach ($requiredConfigs as $config) {
            if (!Configuration::get($config)) {
                $this->warning = $this->l('Missing required configuration: ') . $config;
                return false;
            }
        }
        
        return true;
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function hookDisplayAdminOrderMainBottom($params)
    {
        $order = new Order($params['id_order']);

        if ($order->module !== 'buckaroo3') {
            return;
        }

        $refundProvider = $this->get('buckaroo.refund.admin.provider');
        $refunds = $refundProvider->get($order);
        $this->context->smarty->assign($refunds);

        $buckarooFeeData = (new RawBuckarooFeeRepository())->getFeeByOrderId($order->id);

        // Ensure that $buckarooFeeData is an array
        if (!is_array($buckarooFeeData)) {
            $buckarooFeeData = [
                'buckaroo_fee_tax_excl' => 0,
                'buckaroo_fee_tax_incl' => 0,
                'buckaroo_fee_tax' => 0
            ];
        } else {
            $buckarooFeeData['buckaroo_fee_tax'] = $buckarooFeeData['buckaroo_fee_tax_incl'] - $buckarooFeeData['buckaroo_fee_tax_excl'];
        }

        $this->context->smarty->assign([
            'buckaroo_fee' => $buckarooFeeData,
            'currency' => new Currency($order->id_currency)
        ]);

        // Display both templates
        return $this->display(__FILE__, 'views/templates/hook/payment-fee-table.tpl').
            $this->display(__FILE__, 'views/templates/hook/refund-hook.tpl');
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

    /**
     * Get the core PrestaShop service container
     *
     * @return \PrestaShop\PrestaShop\Adapter\SymfonyContainer|\Symfony\Component\DependencyInjection\ContainerInterface|null
     */
    public function getCoreServiceContainer()
    {
        if ($this->coreServiceContainer instanceof ContainerInterface) {
            return $this->coreServiceContainer;
        }

        $container = $this->getModuleContainerIfAvailable();
        if ($container instanceof ContainerInterface) {
            $this->coreServiceContainer = $container;

            return $this->coreServiceContainer;
        }

        $container = $this->getContextControllerContainer();
        if ($container instanceof ContainerInterface) {
            $this->coreServiceContainer = $container;

            return $this->coreServiceContainer;
        }

        $container = $this->getKernelServiceContainer();
        if ($container instanceof ContainerInterface) {
            $this->coreServiceContainer = $container;

            return $this->coreServiceContainer;
        }

        $container = $this->getLegacySymfonyContainer();
        if ($container instanceof ContainerInterface) {
            $this->coreServiceContainer = $container;

            return $this->coreServiceContainer;
        }

        return null;
    }

    /**
     * @return ContainerInterface|null
     */
    private function getModuleContainerIfAvailable()
    {
        if (!method_exists($this, 'getContainer')) {
            return null;
        }

        try {
            $container = $this->getContainer();
            if ($container instanceof ContainerInterface) {
                return $container;
            }
        } catch (\Throwable $exception) {
            $this->logContainerAccessIssue('Module::getContainer unavailable', $exception);
        }

        return null;
    }

    /**
     * @return ContainerInterface|null
     */
    private function getContextControllerContainer()
    {
        if (!class_exists('\Context')) {
            return null;
        }

        $context = \Context::getContext();
        if (!$context || !isset($context->controller) || !is_object($context->controller)) {
            return null;
        }

        if (!method_exists($context->controller, 'getContainer')) {
            return null;
        }

        try {
            $container = $context->controller->getContainer();
            if ($container instanceof ContainerInterface) {
                return $container;
            }
        } catch (\Throwable $exception) {
            $this->logContainerAccessIssue('Context controller container unavailable', $exception);
        }

        return null;
    }

    /**
     * @return ContainerInterface|null
     */
    private function getKernelServiceContainer()
    {
        global $kernel;

        if ($kernel instanceof KernelInterface) {
            try {
                $container = $kernel->getContainer();
                if ($container instanceof ContainerInterface) {
                    return $container;
                }
            } catch (\Throwable $exception) {
                $this->logContainerAccessIssue('Kernel::getContainer failed', $exception);
            }
        }

        return null;
    }

    /**
     * @return ContainerInterface|null
     */
    private function getLegacySymfonyContainer()
    {
        if (!class_exists('\PrestaShop\PrestaShop\Adapter\SymfonyContainer')) {
            return null;
        }

        try {
            $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
            if ($container instanceof ContainerInterface) {
                return $container;
            }
        } catch (\Throwable $exception) {
            $this->logContainerAccessIssue('SymfonyContainer::getInstance failed', $exception);
        }

        return null;
    }

    /**
     * @param string $serviceId
     *
     * @return mixed|null
     */
    private function getServiceFromAvailableContainers($serviceId)
    {
        if (method_exists($this, 'has')) {
            try {
                if ($this->has($serviceId)) {
                    return $this->get($serviceId);
                }
            } catch (\Throwable $exception) {
                $this->logContainerAccessIssue(
                    sprintf('Unable to resolve "%s" via module container', $serviceId),
                    $exception
                );
            }
        }

        $coreContainer = $this->getCoreServiceContainer();
        if ($coreContainer instanceof ContainerInterface && $coreContainer->has($serviceId)) {
            try {
                return $coreContainer->get($serviceId);
            } catch (\Throwable $exception) {
                $this->logContainerAccessIssue(
                    sprintf('Unable to resolve "%s" via core container', $serviceId),
                    $exception
                );
            }
        }

        return null;
    }

    /**
     * @param string $message
     * @param \Throwable $exception
     */
    private function logContainerAccessIssue($message, \Throwable $exception)
    {
        if (!defined('_PS_MODE_DEV_') || !_PS_MODE_DEV_) {
            return;
        }

        $logMessage = sprintf('[Buckaroo3] %s: %s', $message, $exception->getMessage());
        if (class_exists('\PrestaShopLogger')) {
            \PrestaShopLogger::addLog($logMessage, 2);
        } else {
            error_log($logMessage);
        }
    }

    /**
     * Get CSRF token from token manager
     *
     * @return string
     */
    protected function getCsrfToken(): string
    {
        $tokenManager = $this->getServiceFromAvailableContainers('buckaroo.csrf.token_manager');
        if (!$tokenManager) {
            $tokenManager = $this->getServiceFromAvailableContainers('security.csrf.token_manager');
        }

        if (!$tokenManager) {
            return '';
        }

        $userProvider = $this->getServiceFromAvailableContainers('prestashop.user_provider');
        if (!$userProvider) {
            return '';
        }

        try {
            $token = $tokenManager->getToken($userProvider->getUsername())->getValue();
            if (!empty($token)) {
                return $token;
            }
        } catch (\Throwable $exception) {
            $this->logContainerAccessIssue('Unable to generate CSRF token', $exception);
        }

        return '';
    }

    public function getContent()
    {
        $token = $this->getCsrfToken();

        if (empty($token)) {
            $token = \Tools::getValue('_token');
            if (false === $token || empty($token)) {
                $token = \Tools::getValue('token', '');
            }
        }

        $adminUrl = explode('?', $this->context->link->getAdminLink(AdminDashboard::class))[0];
        $adminUrl = rtrim($adminUrl, '/');
        
        $this->context->smarty->assign([
            'pathApp' => $this->_path . 'views/js/buckaroo.vue.js',
            'baseUrl' => $this->context->shop->getBaseURL(true),
            'adminUrl' => $adminUrl,
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
        PrestaShopLogger::addLog('Buckaroo: hookPaymentOptions() called', 1);
        
        if (!$this->isActivated()) {
            PrestaShopLogger::addLog('Buckaroo: Module not activated, returning empty array', 1);
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
                    'paybybankIssuers' => (new IssuersPayByBank())->get(),
                    'payByBankDisplayMode' => $buckarooConfigService->getConfigValue('paybybank', 'display_type'),
                    'methodsWithFinancialWarning' => $buckarooPaymentService->paymentMethodsWithFinancialWarning(),
                    'creditcardIssuers' => $buckarooConfigService->getActiveCreditCards(),
                    'creditCardDisplayMode' => $buckarooConfigService->getConfigValue('creditcard', 'display_type'),
                    'giftCardDisplayMode' => $buckarooConfigService->getConfigValue('giftcard', 'display_in_checkout'),
                    'in3Method' => $this->get('buckaroo.classes.issuers.capayableIn3')->getMethod(),
                    'buckaroo_idin_test' => $buckarooConfigService->getConfigValue('idin', 'mode'),
                    'houseNumbersAreValid' => $buckarooPaymentService->areHouseNumberValidForCountryDE($cart)
                ]
            );
        } catch (Exception $e) {
            $this->logger->logError('Buckaroo3::hookPaymentOptions - ' . $e->getMessage());
        }

        $this->ensureBuckarooJsLoaded();

        return $buckarooPaymentService->getPaymentOptions($cart);
    }
    
    /**
     * Ensure Buckaroo JavaScript is loaded and JS variables are defined
     */
    private function ensureBuckarooJsLoaded()
    {
        PrestaShopLogger::addLog('Buckaroo: ensureBuckarooJsLoaded() START - Controller: ' . ($this->context->controller ? get_class($this->context->controller) : 'null'), 1);
        
        try {
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
            
            $jsPath = 'modules/' . $this->name . '/views/js/buckaroo.js';
            
            if ($this->context->controller) {
                $this->context->controller->registerJavascript(
                    'module-buckaroo3',
                    $jsPath,
                    [
                        'position' => 'bottom',
                        'priority' => 200,
                    ]
                );
                PrestaShopLogger::addLog('Buckaroo: Script registered successfully. Path: ' . $jsPath, 1);
            } else {
                PrestaShopLogger::addLog('Buckaroo: ERROR - No controller available to register script', 3);
            }
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Buckaroo: ERROR in ensureBuckarooJsLoaded() - ' . $e->getMessage(), 3);
        }
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

        $this->ensureBuckarooJsLoaded();

        if (Tools::getValue('controller') === 'order' && Tools::getValue('buckaroo_error')) {
            $msg = urldecode((string) Tools::getValue('buckaroo_error_msg'));
            Media::addJsDef(['buckaroo_error_msg' => $msg]);

            $this->context->controller->registerJavascript(
                'module-buckaroo-error',
                'modules/'.$this->name.'/views/js/buckaroo-error.js',
                ['position' => 'bottom', 'priority' => 150]
            );
        }

        $this->context->controller->addCSS($this->_path . 'views/css/buckaroo3.css', 'all');
    }

    /**
     * Hook executed at the top of the payment section
     * This is a good place to ensure JS is loaded on checkout page
     */
    public function hookDisplayPaymentTop()
    {
        // Ensure Buckaroo JavaScript is loaded
        $this->ensureBuckarooJsLoaded();
        
        // Return empty string (we just need to load the JS)
        return '';
    }

    /**
     * Resolve a Buckaroo status into a concrete PrestaShop order state.
     *
     * This method is central for status handling and must work across
     * PrestaShop 1.7 and 9.x. It now includes:
     * - safer fallbacks when configured states are missing or deleted
     * - explicit backorder handling using stock information
     *
     * @param int      $status_code Buckaroo payment status constant
     * @param int|null $id_order    Optional order id for stock/backorder checks
     *
     * @return int Valid PrestaShop order state id
     */
    public static function resolveStatusCode($status_code, $id_order = null)
    {
        switch ($status_code) {
            case BuckarooAbstract::BUCKAROO_SUCCESS:
                $isBackOrder = $id_order ? self::isOrderBackOrder($id_order) : false;

                $candidates = [];

                // For successful payments on backorders prefer the out‑of‑stock‑paid state
                if ($isBackOrder) {
                    $candidates[] = Configuration::get('PS_OS_OUTOFSTOCK_PAID');
                }

                // Configured Buckaroo success state and default PrestaShop paid state
                $candidates[] = Configuration::get('BUCKAROO_ORDER_STATE_SUCCESS');
                $candidates[] = Configuration::get('PS_OS_PAYMENT');

                return self::resolveValidOrderStateId($candidates, Configuration::get('PS_OS_PAYMENT'));

            case BuckarooAbstract::BUCKAROO_PENDING_PAYMENT:
                // Pending payments fall back to the module specific default,
                // then to common waiting/pending core states.
                $candidates = [
                    Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT'),
                    Configuration::get('PS_OS_BANKWIRE'),
                    Configuration::get('PS_OS_WAITING_PAYMENT'),
                ];

                return self::resolveValidOrderStateId($candidates, Configuration::get('PS_OS_BANKWIRE'));

            case BuckarooAbstract::BUCKAROO_CANCELED:
            case BuckarooAbstract::BUCKAROO_ERROR:
            case BuckarooAbstract::BUCKAROO_FAILED:
            case BuckarooAbstract::BUCKAROO_INCORRECT_PAYMENT:
                // Failed/cancelled/error map to a configured failure state,
                // then to the generic Cancelled state.
                $candidates = [
                    Configuration::get('BUCKAROO_ORDER_STATE_FAILED'),
                    Configuration::get('PS_OS_CANCELED'),
                ];

                return self::resolveValidOrderStateId($candidates, Configuration::get('PS_OS_ERROR'));

            default:
                // Any unknown status falls back to a generic error.
                return (int) Configuration::get('PS_OS_ERROR');
        }
    }

    /**
     * Return the first valid OrderState id from the list of candidates.
     *
     * This protects against misconfigured or deleted states, which is
     * particularly important on upgraded shops (e.g. PS 9.x).
     *
     * @param array      $candidateIds List of ids (or scalar config values) to try
     * @param int|string $fallbackId   Fallback state id when none of the candidates is valid
     *
     * @return int
     */
    private static function resolveValidOrderStateId(array $candidateIds, $fallbackId)
    {
        foreach ($candidateIds as $candidate) {
            $id = (int) $candidate;
            if ($id <= 0) {
                continue;
            }

            $state = new OrderState($id);
            if (Validate::isLoadedObject($state)) {
                return (int) $state->id;
            }
        }

        $fallback = (int) $fallbackId;
        if ($fallback > 0) {
            $fallbackState = new OrderState($fallback);
            if (Validate::isLoadedObject($fallbackState)) {
                return (int) $fallbackState->id;
            }
        }

        return (int) Configuration::get('PS_OS_ERROR');
    }

    /**
     * Determine whether an order should be treated as a backorder.
     *
     * The previous implementation only checked for negative stock values,
     * which could miss partial backorders. The new logic considers:
     * - global stock management setting
     * - ordered vs. in‑stock quantities per order line
     * - advanced stock via StockAvailable when present
     *
     * @param int|null $orderId
     *
     * @return bool
     */
    private static function isOrderBackOrder($orderId)
    {
        if (!Configuration::get('PS_STOCK_MANAGEMENT')) {
            // If stock management is disabled, treat all orders as non‑backorder
            return false;
        }

        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return false;
        }

        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        $orderDetails = $order->getOrderDetailList();
        if (!is_array($orderDetails) || empty($orderDetails)) {
            return false;
        }

        foreach ($orderDetails as $detail) {
            $orderedQty = (int) $detail['product_quantity'];

            // Quantity that was in stock when the order was placed
            $inStockAtOrder = (int) $detail['product_quantity_in_stock'];

            // If there wasn't enough stock at order time, this line is (at least partly) backordered
            if ($inStockAtOrder < $orderedQty) {
                return true;
            }

            // As an additional safety net, check current stock when available
            if (class_exists('StockAvailable')) {
                $currentQty = (int) StockAvailable::getQuantityAvailableByProduct(
                    (int) $detail['product_id'],
                    (int) $detail['product_attribute_id']
                );

                if ($currentQty < 0 || $currentQty < $orderedQty) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getBuckarooFee($payment_method, $percentageBaseAmount = null)
    {
        $buckarooFee = $this->getBuckarooFeeService()->getBuckarooFeeValue($payment_method);

        if ($buckarooFee === null || $buckarooFee === '') {
            return null;
        }

        $buckarooFee = trim((string) $buckarooFee);

        if ($this->isPercentageFee($buckarooFee)) {
            $percentageValue = $this->normalizePercentageValue($buckarooFee);
            if ($percentageValue <= 0) {
                return null;
            }

            $percentageBase = $this->resolvePercentageBaseAmount($percentageBaseAmount);
            if ($percentageBase <= 0) {
                return null;
            }

            $computePrecision = Context::getContext()->getComputingPrecision();
            $roundedBase = Tools::ps_round($percentageBase, $computePrecision);
            
            $buckarooFee = $roundedBase * ($percentageValue / 100);
        } else {
            $buckarooFee = (float) $buckarooFee;
        }

        if ($buckarooFee <= 0) {
            return null;
        }

        $computePrecision = Context::getContext()->getComputingPrecision();
        $buckarooFee = Tools::ps_round($buckarooFee, $computePrecision);

        $taxRate = $this->getAverageCartTaxRate();
        $buckarooFeeTax = $buckarooFee * $taxRate;
        $buckarooFeeTax = Tools::ps_round($buckarooFeeTax, $computePrecision);
        
        $buckarooFeeTaxIncl = $buckarooFee + $buckarooFeeTax;
        $buckarooFeeTaxIncl = Tools::ps_round($buckarooFeeTaxIncl, $computePrecision);

        return [
            'buckaroo_fee_tax_excl' => $buckarooFee,
            'buckaroo_fee_tax' => $buckarooFeeTax,
            'buckaroo_fee_tax_incl' => $buckarooFeeTaxIncl,
        ];
    }

    private function isPercentageFee($fee): bool
    {
        return is_string($fee) && strpos($fee, '%') !== false;
    }

    private function normalizePercentageValue($fee): float
    {
        $normalized = str_replace('%', '', $fee);
        $normalized = str_replace(',', '.', $normalized);

        return (float) trim($normalized);
    }

    private function resolvePercentageBaseAmount($overrideAmount = null): float
    {
        if ($overrideAmount !== null) {
            return (float) $overrideAmount;
        }

        if ($this->context && isset($this->context->cart) && $this->context->cart instanceof Cart) {
            return (float) $this->context->cart->getOrderTotal(true, Cart::BOTH);
        }

        return 0.0;
    }

    private function getAverageCartTaxRate(): float
    {
        if ($this->context && isset($this->context->cart) && $this->context->cart instanceof Cart) {
            return (float) $this->context->cart->getAverageProductsTaxRate();
        }

        return 0.0;
    }

    public function hookActionEmailSendBefore($params)
    {
        if (!isset($params['cart']->id)) {
            return true;
        }

        $cart = new Cart($params['cart']->id);
        $orderId = Order::getIdByCartId($cart->id);
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

            
            $cartTotal = (float) $cart->getOrderTotal(true, Cart::BOTH);
            $buckarooFee = $this->getBuckarooFee($paymentMethodName, $cartTotal);

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
            $currency = new Currency($order->id_currency);
            $context = Context::getContext();

            $params['templateVars']['{payment_fee_label}'] = $paymentFeeLabel;

            if ($buckarooFeeTaxIncl > 0) {
                $params['templateVars']['{payment_fee}'] = $context->getCurrentLocale()->formatPrice($buckarooFeeTaxExcl, $currency->iso_code);
                $params['templateVars']['{payment_fee_tax}'] = $context->getCurrentLocale()->formatPrice($buckarooFee['buckaroo_fee_tax'], $currency->iso_code);
                $params['templateVars']['{total_paid}'] = $context->getCurrentLocale()->formatPrice($order->total_paid + $buckarooFeeTaxIncl, $currency->iso_code);
                // Include the total tax paid, which includes the payment fee tax
                $totalTaxPaid = $order->total_paid_tax_incl - $order->total_paid_tax_excl + $buckarooFee['buckaroo_fee_tax'];
                $params['templateVars']['{total_tax_paid}'] = $context->getCurrentLocale()->formatPrice($totalTaxPaid, $currency->iso_code);
            } else {
                $params['templateVars']['{payment_fee}'] = $context->getCurrentLocale()->formatPrice(0, $currency->iso_code);
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
                'order_buckaroo_fee_tax' => $this->formatPrice($taxData['taxAmount']),
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
