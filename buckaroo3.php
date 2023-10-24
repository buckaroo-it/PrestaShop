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
require_once _PS_ROOT_DIR_ . '/modules/buckaroo3/vendor/autoload.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/api/paymentmethods/responsefactory.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/common.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/library/logger.php';

use Buckaroo\BuckarooClient;
use Buckaroo\PrestaShop\Classes\CapayableIn3;
use Buckaroo\PrestaShop\Classes\IssuersIdeal;
use Buckaroo\PrestaShop\Classes\IssuersPayByBank;
use Buckaroo\PrestaShop\Classes\JWTAuth;
use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;
use Buckaroo\PrestaShop\Src\Entity\BkCountries;
use Buckaroo\PrestaShop\Src\Entity\BkOrdering;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Buckaroo\PrestaShop\Src\Form\Modifier\ProductFormModifier;
use Buckaroo\PrestaShop\Src\Install\DatabaseTableInstaller;
use Buckaroo\PrestaShop\Src\Install\DatabaseTableUninstaller;
use Buckaroo\PrestaShop\Src\Install\IdinColumnsRemover;
use Buckaroo\PrestaShop\Src\Install\Installer;
use Buckaroo\PrestaShop\Src\Install\Uninstaller;
use Buckaroo\PrestaShop\Src\Refund\Settings as RefundSettings;
use Buckaroo\PrestaShop\Src\Repository\BkConfigurationRepositoryInterface;
use Buckaroo\PrestaShop\Src\Repository\BkCountriesRepositoryInterface;
use Buckaroo\PrestaShop\Src\Repository\BkPaymentMethodRepositoryInterface;
use Buckaroo\PrestaShop\Src\Repository\RawPaymentMethodRepository;
use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;
use Buckaroo\PrestaShop\Src\Service\BuckarooCountriesService;
use Buckaroo\PrestaShop\Src\Service\BuckarooFeeService;
    use Buckaroo\PrestaShop\Src\Service\BuckarooIdinService;
    use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentService;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;

class Buckaroo3 extends PaymentModule
{
    public $buckarooPaymentService;
    public $buckarooFeeService;
    public $buckarooConfigService;
    public $buckarooCountriesService;
    public $bkOrderingRepository;
    private $issuersPayByBank;
    private $issuersCreditCard;
    private $capayableIn3;
    public $symContainer;
    public $entityManager;
    public $logger;
    private $locale;

    public function __construct()
    {
        $this->name = 'buckaroo3';
        $this->tab = 'payments_gateways';
        $this->version = '4.0.1';
        $this->author = 'Buckaroo';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->module_key = '8d2a2f65a77a8021da5d5ffccc9bbd2b';
        $this->ps_versions_compliancy = ['min' => '1', 'max' => _PS_VERSION_];
        parent::__construct();
        $this->setContainer();

        $this->displayName = $this->l('Buckaroo Payments') . ' (v ' . $this->version . ')';
        $this->description = $this->l('Buckaroo Payment module. Compatible with PrestaShop version 1.7.x + 8.1.2');

        $this->confirmUninstall = $this->l('Are you sure you want to delete Buckaroo Payments module?');
        $this->tpl_folder = 'buckaroo3';
        $this->logger = new \Logger(CoreLogger::INFO, '');
        $this->locale = \Tools::getContextLocale($this->context);

        $response = ResponseFactory::getResponse();
        if ($response) {
            if ($response->isValid()) {
                if ($response->brq_transaction_type == 'I150') {
                    $this->displayName = 'Group transaction';
                } else {
                    if ($response->hasSucceeded()) {
                        $this->displayName = $response->payment_method;
                    } else {
                        if (isset($response->status) && $response->status > 0) {
                            $this->displayName = (new RawPaymentMethodRepository())->getPaymentMethodsLabel($response->payment_method);
                        } else {
                            $this->displayName = $this->l('Buckaroo Payments (v 4.0.1)');
                        }
                    }
                }
            }
        }
        if (!Configuration::get('BUCKAROO_MERCHANT_KEY')
            || !Configuration::get('BUCKAROO_SECRET_KEY')
            || !Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT')
            || !Configuration::get('BUCKAROO_ORDER_STATE_SUCCESS')
            || !Configuration::get('BUCKAROO_ORDER_STATE_FAILED')
        ) {
            return '';
        }

        $translations = [];
        $translations[] = $this->l('Your payment was unsuccessful. Please try again or choose another payment method.');
        $translations[] = $this->l('Order confirmation');
        $translations[] = $this->l('current_step');
        $translations[] = $this->l('Your order  is complete.');
        $translations[] = $this->l('You have chosen the');
        $translations[] = $this->l('payment method.');
        $translations[] = $this->l('Your order will be sent very soon.');
        $translations[] = $this->l(
            'For any questions or for further information, please contact our customer support.'
        );
        $translations[] = $this->l('Total of the transaction (taxes incl.) :');
        $translations[] = $this->l('Your order reference ID is :');
        $translations[] = $this->l('Back to orders');
        $translations[] = $this->l('Follow my order');
        $translations[] = $this->l('Payment in progress');
        $translations[] = $this->l('Buckaroo supports the following gift cards:');
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function hookDisplayAdminOrderMainBottom($params)
    {
        $refundProvider = $this->get('buckaroo.refund.admin.provider');

        $this->smarty->assign(
            $refundProvider->get(
                new Order($params['id_order'])
            )
        );

        return $this->display(__FILE__, 'views/templates/hook/refund-hook.tpl');
    }

    /**
     * @throws LocalizationException
     */
    public function hookDisplayOrderConfirmation(array $params)
    {
        $order = isset($params['objOrder']) ? $params['objOrder'] : null;
        $order = isset($params['order']) ? $params['order'] : $order;
        if (!$order) {
            return '';
        }
        $cart = new Cart($order->id_cart);

        if (!$cart) {
            return '';
        }

        $buckarooFee = $this->getBuckarooFeeByCartId($cart->id);
        if (!$buckarooFee) {
            return '';
        }
        $sql = 'UPDATE `' . _DB_PREFIX_ . "orders` SET total_paid_tax_incl = '" . $order->total_paid .
            "' WHERE id_cart = '" . $cart->id . "'";
        Db::getInstance()->execute($sql);

        return '<script>
        document.addEventListener("DOMContentLoaded", function(){
            $(".total-value").before($("<tr><td>Buckaroo Fee</td><td>' . $this->formatPrice($buckarooFee) . '</td></tr>"))
            });
        </script>';
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

        $databaseTableInstaller = new DatabaseTableInstaller();

        $coreInstaller = new Installer($this, $databaseTableInstaller);

        if (!$coreInstaller->install()) {
            $this->_errors = array_merge($this->_errors, $coreInstaller->getErrors());

            return false;
        }

        (new RefundSettings())->install();

        $states = OrderState::getOrderStates((int) Configuration::get('PS_LANG_DEFAULT'));

        $currentStates = [];
        foreach ($states as $state) {
            $state = (object) $state;
            $currentStates[$state->id_order_state] = $state->name;
        }

        if (($state_id = array_search($this->l('Awaiting for Remote payment'), $currentStates)) === false) {
            // Add the custom order state
            $defaultOrderState = new OrderState();
            $defaultOrderState->name = [
                Configuration::get('PS_LANG_DEFAULT') => $this->l(
                    'Awaiting for Remote payment'
                ),
            ];
            $defaultOrderState->module_name = $this->name;
            $defaultOrderState->send_mail = 0;
            $defaultOrderState->template = '';
            $defaultOrderState->invoice = 0;
            $defaultOrderState->color = '#FFF000';
            $defaultOrderState->unremovable = false;
            $defaultOrderState->logable = 0;
            if ($defaultOrderState->add()) {
                $source = dirname(__FILE__) . '/logo.gif';
                $destination = dirname(__FILE__) . '/../../img/os/' . (int) $defaultOrderState->id . '.gif';
                if (!file_exists($destination)) {
                    copy($source, $destination);
                }
            }
        } else {
            $defaultOrderState = new stdClass();
            $defaultOrderState->id = $state_id;
        }

        Configuration::updateValue('BUCKAROO_ORDER_STATE_DEFAULT', $defaultOrderState->id);
        Configuration::updateValue('BUCKAROO_ORDER_STATE_SUCCESS', Configuration::get('PS_OS_PAYMENT'));
        Configuration::updateValue('BUCKAROO_ORDER_STATE_FAILED', Configuration::get('PS_OS_CANCELED'));

        // Cookie SameSite fix
        Configuration::updateValue('PS_COOKIE_SAMESITE', 'None');

        return true;
    }

    public function uninstall()
    {
        $databaseTableUninstaller = new DatabaseTableUninstaller();
        $databaseIdinColumnsRemover = new IdinColumnsRemover();
        $uninstall = new Uninstaller($this, $databaseTableUninstaller, $databaseIdinColumnsRemover);

        if (!$uninstall->uninstall()) {
            $this->_errors[] = $uninstall->getErrors();

            return false;
        }

        $refundSettingsService = $this->get('buckaroo.refund.settings');
        if ($refundSettingsService) {
            $refundSettingsService->uninstall();
        }

        return parent::uninstall();
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/buckaroo3.admin.css', 'all');
    }

    public function getContent()
    {
        $jwt = new JWTAuth();
        $token = $this->generateToken($jwt);
        $this->context->smarty->assign([
            'pathApp' => $this->getPathUri() . 'dev/assets/main.39e55f8f.js',
            'pathCss' => $this->getPathUri() . 'dev/assets/main.1885b933.css',
            'baseUrl' => $this->context->shop->getBaseURL(true),
            'jwt' => $token,
        ]);

        return $this->context->smarty->fetch('module:buckaroo3/views/templates/admin/app.tpl');
    }

    private function generateToken($jwt)
    {
        $data = [];

        if ($this->context->employee->isLoggedBack()) {
            $data = ['employee_id' => $this->context->employee->id];
        } elseif ($this->context->customer->isLogged()) {
            $data = ['user_id' => $this->context->customer->id];
        }

        return $jwt->encode($data);
    }

    private function isActivated()
    {
        $websiteKey = Configuration::get('BUCKAROO_MERCHANT_KEY');
        $secretKey = Configuration::get('BUCKAROO_SECRET_KEY');

        return $this->checkKeys($websiteKey, $secretKey);
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
        if (!$this->active || !$this->isActivated()) {
            return;
        }

        $cookie = new Cookie('ps');
        $cart = new Cart($params['cookie']->__get('id_cart'));
        $customer = new Customer($cart->id_customer);
        $cookie_id_lang = (int) $cookie->id_lang;
        $id_lang = $cookie_id_lang ? $cookie_id_lang : (int) (Configuration::get('PS_LANG_DEFAULT'));
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
        }
        if (empty($phone_afterpay_billing) && !empty($phone_billing)) {
            $phone_afterpay_billing = $phone_billing;
        }

        $address_differ = 0;

        if ($cart->id_address_delivery != $cart->id_address_invoice
            && $lastNameShipping == $lastNameBilling
            && $firstNameShipping == $firstNameBilling) {
            $address_differ = 2;
        } elseif ($cart->id_address_delivery != $cart->id_address_invoice) {
            $address_differ = 1;
        }

        $this->issuersPayByBank = new IssuersPayByBank();

        $this->issuersCreditCard = $this->getBuckarooConfigService()->getActiveCreditCards();

        $this->capayableIn3 = new CapayableIn3();

        $this->entityManager = $this->get('doctrine.orm.entity_manager');

        $bkPaymentMethodRepository = $this->getRepository(BkPaymentMethods::class, BkPaymentMethodRepositoryInterface::class);

        $bkOrderingRepository = $this->getBuckarooOrderingRepository();

        $this->buckarooPaymentService = new BuckarooPaymentService(
            $this,
            $this->getBuckarooConfigService(),
            $this->issuersPayByBank,
            $this->logger,
            $this->context,
            $this->capayableIn3,
            $this->getBuckarooFeeService(),
            $bkOrderingRepository,
            $bkPaymentMethodRepository
        );

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
                    'afterpay_show_coc' => $this->buckarooPaymentService->showAfterpayCoc($cart),
                    'billink_show_coc' => $this->buckarooPaymentService->showBillinkCoc($cart),
                    'idealIssuers' => (new IssuersIdeal())->get(),
                    'idealDisplayMode' => $this->buckarooConfigService->getConfigValue('ideal', 'display_type'),
                    'paybybankIssuers' => $this->issuersPayByBank->getIssuerList(),
                    'payByBankDisplayMode' => $this->buckarooConfigService->getConfigValue('paybybank', 'display_type'),
                    'creditcardIssuers' => $this->issuersCreditCard,
                    'creditCardDisplayMode' => $this->buckarooConfigService->getConfigValue('creditcard', 'display_type'),
                    'in3Method' => (new CapayableIn3())->getMethod(),
                ]
            );
        } catch (Exception $e) {
            $this->logger->logError('Buckaroo3::hookPaymentOptions - ' . $e->getMessage());
        }

        return $this->buckarooPaymentService->getPaymentOptions($cart);
    }

    public function getEntityManager()
    {
        if (!$this->entityManager) {
            $this->entityManager = $this->symContainer->get('doctrine.orm.entity_manager');
        }

        return $this->entityManager;
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

        if (Tools::getValue('response_received')) {
            switch (Tools::getValue('response_received')) {
                case 'transfer':
                    $order = new Order(Tools::getValue('id_order'));
                    $price = $order->getOrdersTotalPaid();
                    $message = $this->context->cookie->HtmlText;
                    $this->context->smarty->assign(
                        [
                            'is_guest' => ($this->context->customer->is_guest
                                || $this->context->customer->id == false),
                            'order' => $order,
                            'message' => $message,
                            'price' => $this->formatPrice($price),
                        ]
                    );

                    return $this->display(__FILE__, 'payment_return_redirectsuccess.tpl');
                default:
                    $order = new Order(Tools::getValue('id_order'));
                    $price = $order->getOrdersTotalPaid();
                    $this->context->smarty->assign(
                        [
                            'is_guest' => ($this->context->customer->is_guest
                                || $this->context->customer->id == false),
                            'order' => $order,
                            'price' => $this->formatPrice($price),
                        ]
                    );

                    return $this->display(__FILE__, 'payment_return_success.tpl');
            }
        } else {
            if (Tools::getValue('id_order') && Tools::getValue('success')) {
                $order = new Order(Tools::getValue('id_order'));
                if ($order) {
                    $price = $order->getOrdersTotalPaid();
                    $this->context->smarty->assign(
                        [
                            'is_guest' => ($this->context->customer->is_guest || $this->context->customer->id == false), // phpcs:ignore
                            'order' => $order,
                            'price' => $this->formatPrice($price),
                        ]
                    );

                    return $this->display(__FILE__, 'payment_return_success.tpl');
                } else {
                    Tools::redirect('index.php?fc=module&module=buckaroo3&controller=error');
                    exit;
                }
            } else {
                Tools::redirect('index.php?fc=module&module=buckaroo3&controller=error');
                exit;
            }
        }
    }

    public function hookDisplayHeader()
    {
        $buckarooFeeService = $this->getBuckarooFeeService();

        Media::addJsDef([
            'buckarooAjaxUrl' => $this->context->link->getModuleLink('buckaroo3', 'ajax'),
            'buckarooFees' => $buckarooFeeService->getBuckarooFees(),
            'buckarooMessages' => [
                'validation' => [
                    'date' => $this->l('Please enter correct birthdate date'),
                    'required' => $this->l('Field is required'),
                    'agreement' => $this->l('Please accept licence agreements'),
                    'iban' => $this->l('A valid IBAN is required'),
                    'age' => $this->l('You must be at least 18 years old'),
                ],
            ],
        ]);

        $this->context->controller->addCSS($this->_path . 'views/css/buckaroo3.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/buckaroo.js', 'all');
    }

    public static function resolveStatusCode($status_code)
    {
        switch ($status_code) {
            case BuckarooAbstract::BUCKAROO_SUCCESS:
                return Configuration::get('BUCKAROO_ORDER_STATE_SUCCESS') ?
                    Configuration::get('BUCKAROO_ORDER_STATE_SUCCESS') : Configuration::get('PS_OS_PAYMENT');
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

    public function getBuckarooFeeByCartId($id_cart)
    {
        $sql = 'SELECT buckaroo_fee FROM ' . _DB_PREFIX_ . 'buckaroo_fee where id_cart = ' . (int) $id_cart;

        return Db::getInstance()->getValue($sql);
    }

    /**
     * @throws LocalizationException
     */
    public function hookActionEmailSendBefore($params)
    {
        if (!isset($params['cart']->id)) {
            return true;
        }

        $cart = new Cart($params['cart']->id);
        if (Order::getByCartId($cart->id)->module !== $this->name) {
            return true;
        }

        if ($params['template'] === 'order_conf'
            || $params['template'] === 'account'
            || $params['template'] === 'backoffice_order'
            || $params['template'] === 'contact_form'
            || $params['template'] === 'credit_slip'
            || $params['template'] === 'in_transit'
            || $params['template'] === 'order_changed'
            || $params['template'] === 'order_merchant_comment'
            || $params['template'] === 'order_return_state'
            || $params['template'] === 'cheque'
            || $params['template'] === 'payment'
            || $params['template'] === 'preparation'
            || $params['template'] === 'shipped'
            || $params['template'] === 'order_canceled'
            || $params['template'] === 'payment_error'
            || $params['template'] === 'outofstock'
            || $params['template'] === 'bankwire'
            || $params['template'] === 'refund') {
            $order = Order::getByCartId($cart->id);
            if (!$order) {
                return true;
            }

            $buckarooFee = $this->getBuckarooFeeByCartId($cart->id);
            if ($buckarooFee) {
                $params['templateVars']['{buckaroo_fee}'] = $this->formatPrice($buckarooFee);
            } else {
                $params['templateVars']['{buckaroo_fee}'] = $this->formatPrice(0);
            }
        }
    }

    /**
     * @throws SmartyException
     * @throws LocalizationException
     */
    public function hookDisplayPDFInvoice($params)
    {
        if ($params['object'] instanceof OrderInvoice) {
            $order = $params['object']->getOrder();

            $buckarooFee = $this->getBuckarooFeeByCartId(Cart::getCartIdByOrderId($order->id));

            if (!$buckarooFee) {
                return;
            }

            $this->context->smarty->assign(
                [
                    'order_buckaroo_fee' => $this->formatPrice($buckarooFee),
                ]
            );

            return $this->context->smarty->fetch(
                $this->getLocalPath() . 'views/templates/admin/invoice_fee.tpl'
            );
        }
    }

    public function isPaymentModeActive($method)
    {
        $isLive = (int) \Configuration::get(Config::BUCKAROO_TEST);
        $configArray = $this->buckarooConfigService->getConfigArrayForMethod($method);
        if ($configArray === null) {
            return false;
        }

        if ($isLive === 0) {
            return isset($configArray['mode']) && $configArray['mode'] === 'test';
        } elseif ($isLive === 1) {
            return isset($configArray['mode']) && $configArray['mode'] === 'live';
        }

        return false;
    }

    public function isIdinProductBoxShow($params)
    {
        $buckarooConfigService = $this->getBuckarooConfigService();

        if (!$this->isPaymentModeActive('idin')) {
            return false;
        }

        switch ($buckarooConfigService->getConfigValue('idin', 'display_mode')) {
            case 'product':
                return $this->isProductBuckarooIdinEnabled($params['product']->id);
            case 'global':
                return true;
            default:
                return false;
        }
    }

    private function isProductBuckarooIdinEnabled($productId)
    {
        $sql = 'SELECT buckaroo_idin FROM ' . _DB_PREFIX_ . 'bk_product_idin WHERE product_id = ' . (int) $productId;
        $buckarooIdin = Db::getInstance()->getValue($sql);

        return $buckarooIdin == 1;
    }

    public function isIdinCheckout($cart)
    {
        $buckarooConfigService = $this->getBuckarooConfigService();

        if (!$this->isPaymentModeActive('idin')) {
            return false;
        }

        switch ($buckarooConfigService->getConfigValue('idin', 'display_mode')) {
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

    public function getBuckarooCountriesService()
    {
        if (!isset($this->buckarooCountriesService)) {
            $bkCountriesRepository = $this->getRepository(BkCountries::class, BkCountriesRepositoryInterface::class);
            $this->buckarooCountriesService = new BuckarooCountriesService($bkCountriesRepository);
        }

        return $this->buckarooCountriesService;
    }

    public function getBuckarooOrderingRepository()
    {
        if (!isset($this->bkOrderingRepository)) {
            $this->bkOrderingRepository = $this->getEntityManager()->getRepository(BkOrdering::class);
        }

        return $this->bkOrderingRepository;
    }

    public function getBuckarooConfigService()
    {
        if (!isset($this->buckarooConfigService)) {
            $bkPaymentMethodRepository = $this->getRepository(BkPaymentMethods::class, BkPaymentMethodRepositoryInterface::class);
            $bkOrderingRepository = $this->getBuckarooOrderingRepository();
            $bkConfigurationRepository = $this->getRepository(BkConfiguration::class, BkConfigurationRepositoryInterface::class);

            $this->buckarooConfigService = new BuckarooConfigService($bkPaymentMethodRepository, $bkOrderingRepository, $bkConfigurationRepository);
        }

        return $this->buckarooConfigService;
    }

    public function getBuckarooFeeService()
    {
        if (!isset($this->buckarooFeeService)) {
            $bkPaymentMethodRepository = $this->getRepository(BkPaymentMethods::class, BkPaymentMethodRepositoryInterface::class);
            $bkConfigurationRepository = $this->getRepository(BkConfiguration::class, BkConfigurationRepositoryInterface::class);

            $this->buckarooFeeService = new BuckarooFeeService($bkConfigurationRepository, $bkPaymentMethodRepository, $this->logger);
        }

        return $this->buckarooFeeService;
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
        $productId = (int) $params['id'];

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

    private function getRepository($class, $expectedInterface = null)
    {
        $repository = $this->getEntityManager()->getRepository($class);

        if ($expectedInterface && !$repository instanceof $expectedInterface) {
            throw new \RuntimeException("The {$class} repository must implement {$expectedInterface}.");
        }

        return $repository;
    }

    private function setContainer()
    {
        global $kernel;

        if (!$kernel) {
            require_once _PS_ROOT_DIR_ . '/app/AppKernel.php';
            $kernel = new \AppKernel('prod', false);
            $kernel->boot();
        }
        $this->symContainer = $kernel->getContainer();
    }

    /**
     * @throws LocalizationException
     */
    private function formatPrice($amount): string
    {
        $currency = \Context::getContext()->currency;

        return $this->locale->formatPrice($amount, $currency->iso_code);
    }
}
