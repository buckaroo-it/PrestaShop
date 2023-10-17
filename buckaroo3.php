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
require_once _PS_MODULE_DIR_ . 'buckaroo3/library/logger.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/common.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/classes/IssuersIdeal.php';

use Buckaroo\BuckarooClient;
use Buckaroo\PrestaShop\Classes\CapayableIn3;
use Buckaroo\PrestaShop\Classes\IssuersPayByBank;
use Buckaroo\PrestaShop\Classes\JWTAuth;
use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\Form\Modifier\ProductFormModifier;
use Buckaroo\PrestaShop\Src\Install\DatabaseTableInstaller;
use Buckaroo\PrestaShop\Src\Install\DatabaseTableUninstaller;
use Buckaroo\PrestaShop\Src\Install\IdinColumnsRemover;
use Buckaroo\PrestaShop\Src\Install\Installer;
use Buckaroo\PrestaShop\Src\Install\Uninstaller;
use Buckaroo\PrestaShop\Src\Refund\Settings as RefundSettings;
use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;
use Buckaroo\PrestaShop\Src\Service\BuckarooFeeService;
use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentService;

class Buckaroo3 extends PaymentModule
{
    public $context;

    /** @var BuckarooPaymentService */
    private $buckarooPaymentService;

    /** @var BuckarooFeeService */
    private $buckarooFeeService;

    /** @var BuckarooConfigService */
    private $buckarooConfigService;

    public $logger;
    private $issuersPayByBank;
    private $issuersCreditCard;
    private $capayableIn3;

    public $symContainer;

    public function __construct()
    {
        $this->name = 'buckaroo3';
        $this->tab = 'payments_gateways';
        $this->version = '3.5.0';
        $this->author = 'Buckaroo';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->module_key = '8d2a2f65a77a8021da5d5ffccc9bbd2b';
        $this->ps_versions_compliancy = ['min' => '1', 'max' => _PS_VERSION_];
        parent::__construct();
        $this->setContainer();

        $this->displayName = $this->l('Buckaroo Payments') . ' (v ' . $this->version . ')';
        $this->description = $this->l('Buckaroo Payment module. Compatible with PrestaShop version 1.6.x + 1.7.x');

        $this->confirmUninstall = $this->l('Are you sure you want to delete Buckaroo Payments module?');
        $this->tpl_folder = 'buckaroo3';

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
                            $this->displayName = $this->getPaymentTranslation($response->payment_method);
                        } else {
                            $this->displayName = $this->l('Buckaroo Payments (v 3.4.0)');
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

        $currency = new Currency((int) $order->id_currency);
        $buckarooFee = Tools::displayPrice($buckarooFee, $currency, false);

        $return = '<script>
        document.addEventListener("DOMContentLoaded", function(){
            $(".total-value").before($("<tr><td>Buckaroo Fee</td><td>' . $buckarooFee . '</td></tr>"))
            });
        </script>';

        return $return;
    }

    public function addBuckarooIdin()
    {
        Db::getInstance()->query('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'customer` LIKE "buckaroo_idin_%"');
        if (Db::getInstance()->NumRows() == 0) {
            Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'customer` 
            ADD buckaroo_idin_consumerbin VARCHAR(255) NULL, ADD buckaroo_idin_iseighteenorolder VARCHAR(255) NULL;');
        }

        Db::getInstance()->query('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'product` LIKE "buckaroo_idin"');
        if (Db::getInstance()->NumRows() == 0) {
            Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'product`
            ADD `buckaroo_idin` TINYINT(1) UNSIGNED DEFAULT 0');
        }
    }

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

        $this->addBuckarooIdin();

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
        $uninstall = new Uninstaller($databaseTableUninstaller,$databaseIdinColumnsRemover);

        if (!$uninstall->uninstall()) {
            $this->_errors[] = $uninstall->getErrors();

            return false;
        }

        $this->unregisterHook('displayBackOfficeHeader');
        $this->unregisterHook('displayAdminOrderMainBottom');
        $this->unregisterHook('displayOrderConfirmation');
        $this->unregisterHook('actionEmailSendBefore');
        $this->unregisterHook('displayPDFInvoice');

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
            'pathApp' => $this->getPathUri() . 'dev/assets/main.c75d6aea.js',
            'pathCss' => $this->getPathUri() . 'dev/assets/main.ffb95ec5.css',
            'jwt' => $token,
        ]);

        return $this->context->smarty->fetch('module:buckaroo3/views/templates/admin/app.tpl');
    }

    private function generateToken($jwt)
    {
        $context = Context::getContext();
        $data = [];

        if ($context->employee->isLoggedBack()) {
            $data = ['employee_id' => $context->employee->id];
        } elseif ($context->customer->isLogged()) {
            $data = ['user_id' => $context->customer->id];
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

        if (!$this->active) {
            return;
        }

        if (!$this->isActivated()) {
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

        $this->initBuckarooConfigService();

        $this->logger = new Logger(Logger::INFO, $fileName = '');
        $this->issuersPayByBank = new IssuersPayByBank();

        $this->issuersCreditCard = $this->buckarooConfigService->getActiveCreditCards();

        $this->capayableIn3 = new CapayableIn3();

        $entityManager = $this->get('doctrine.orm.entity_manager');

        $this->buckarooPaymentService = new BuckarooPaymentService(
            $entityManager,
            $this,
            $this->buckarooConfigService,
            $this->issuersPayByBank,
            $this->logger,
            $this->context,
            $this->capayableIn3
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
                    'idealDisplayMode' => $this->buckarooConfigService->getSpecificValueFromConfig('ideal', 'display_type'),
                    'paybybankIssuers' => $this->issuersPayByBank->getIssuerList(),
                    'payByBankDisplayMode' => $this->buckarooConfigService->getSpecificValueFromConfig('paybybank', 'display_type'),
                    'creditcardIssuers' => $this->issuersCreditCard,
                    'creditCardDisplayMode' => $this->buckarooConfigService->getSpecificValueFromConfig('creditcard', 'display_type'),
                    'in3Method' => (new CapayableIn3())->getMethod(),
                ]
            );
        } catch (Exception $e) {
            $this->logger->logInfo('Buckaroo3::hookPaymentOptions - ' . $e->getMessage(), 'error');
        }

        return $this->buckarooPaymentService->getPaymentOptions($cart);
    }

    public function getEntityManager()
    {
        return $this->symContainer->get('doctrine.orm.entity_manager');
    }

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
                            'price' => Tools::displayPrice($price, $this->context->currency->id),
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
                            'price' => Tools::displayPrice($price, $this->context->currency->id),
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
                            'price' => Tools::displayPrice($price, $this->context->currency->id),
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
        $this->buckarooFeeService = new BuckarooFeeService($this->getEntityManager(),$this->logger);

        Media::addJsDef([
            'buckarooAjaxUrl' => $this->context->link->getModuleLink('buckaroo3', 'ajax'),
            'buckarooFees' => $this->buckarooFeeService->getBuckarooFees(),
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

    public function getPaymentTranslation($payment_method)
    {
        switch ($payment_method) {
            case 'paypal':
                $payment_method_tr = $this->l('PayPal');
                break;
            case 'sepadirectdebit':
                $payment_method_tr = $this->l('Sepa Direct Debit');
                break;
            case 'ideal':
                $payment_method_tr = $this->l('iDEAL');
                break;
            case 'giropay':
                $payment_method_tr = $this->l('Giro Pay');
                break;
            case 'kbc':
                $payment_method_tr = $this->l('KBC Pay');
                break;
            case 'bancontactmrcash':
                $payment_method_tr = $this->l('Bancontact / MisterCash');
                break;
            case 'maestro':
                $payment_method_tr = $this->l('Maestro');
                break;
            case 'sofortueberweisung':
                $payment_method_tr = $this->l('Sofort banking');
                break;
            case 'belfius':
                $payment_method_tr = $this->l('Belfius');
                break;
            case 'cashticket':
                $payment_method_tr = $this->l('Cash Ticket');
                break;
            case 'transfer':
                $payment_method_tr = $this->l('Transfer');
                break;
            case 'afterpay':
                $payment_method_tr = $this->l('Riverty | AfterPay');
                break;
            case 'klarna':
                $payment_method_tr = $this->l('Klarna');
                break;
            case 'giftcard':
                $payment_method_tr = $this->l('GiftCard');
                break;
            case 'creditcard':
                $payment_method_tr = $this->l('CreditCard');
                break;
            case 'applepay':
                $payment_method_tr = $this->l('Apple Pay');
                break;
            case 'in3':
                $payment_method_tr = $this->l('In3');
                break;
            case 'billink':
                $payment_method_tr = $this->l('Billink');
                break;
            case 'eps':
                $payment_method_tr = $this->l('EPS');
                break;
            case 'przelewy24':
                $payment_method_tr = $this->l('Przelewy24');
                break;
            case 'tinka':
                $payment_method_tr = $this->l('Tinka');
                break;
            case 'trustly':
                $payment_method_tr = $this->l('Trustly');
                break;
            case 'payperemail':
                $payment_method_tr = $this->l('PayPerEmail');
                break;
            case 'payconiq':
                $payment_method_tr = $this->l('Payconiq');
                break;
            case 'paybybank':
                $payment_method_tr = $this->l('PayByBank');
                break;
            default:
                $payment_method_tr = $this->l($payment_method);
                break;
        }

        return $payment_method_tr;
    }

    public function getBuckarooFeeByCartId($id_cart)
    {
        $sql = 'SELECT buckaroo_fee FROM ' . _DB_PREFIX_ . 'buckaroo_fee where id_cart = ' . (int) $id_cart;

        return Db::getInstance()->getValue($sql);
    }

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
                $params['templateVars']['{buckaroo_fee}'] = Tools::displayPrice($buckarooFee);
            } else {
                $params['templateVars']['{buckaroo_fee}'] = Tools::displayPrice(0);
            }
        }
    }

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
                    'order_buckaroo_fee' => Tools::displayPrice($buckarooFee),
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
        } else if ($isLive === 1) {
            return isset($configArray['mode']) && $configArray['mode'] === 'live';
        }

        return false;
    }

    public function isIdinProductBoxShow($params)
    {
        $this->initBuckarooConfigService();

        if (!$this->isPaymentModeActive('idin')) {
            return false;
        }

        switch ($this->buckarooConfigService->getSpecificValueFromConfig('idin', 'display_mode')) {
            case 'product':
                return isset($params['product']->buckaroo_idin) && $params['product']->buckaroo_idin == 1;
            case 'global':
                return true;
            default:
                return false;
        }
    }

    public function isIdinCheckout($cart)
    {
        $this->initBuckarooConfigService();

        if (!$this->isPaymentModeActive('idin')) {
            return false;
        }

        switch ($this->buckarooConfigService->getSpecificValueFromConfig('idin', 'display_mode')) {
            case 'product':
                foreach ($cart->getProducts(true) as $value) {
                    $product = new Product($value['id_product']);
                    if (isset($product->buckaroo_idin) && $product->buckaroo_idin == 1) {
                        return true;
                    }
                }
                break;
            case 'global':
                return true;
            default:
                return false;
        }

        return false;
    }

    private function initBuckarooConfigService()
    {
        if (!isset($this->buckarooConfigService)) {
            $this->buckarooConfigService = new BuckarooConfigService($this->getEntityManager());
        }
    }

    public function hookActionAdminCustomersListingFieldsModifier($params)
    {
        $params['fields']['buckaroo_idin_consumerbin'] = [
            'title' => $this->l('iDIN Consumerbin'),
            'align' => 'center',
        ];

        $params['fields']['buckaroo_idin_iseighteenorolder'] = [
            'title' => $this->l('iDIN isEighteenOrOlder'),
            'align' => 'center',
        ];
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
        $this->updateCustomerReviewStatus($params);
    }

    private function updateCustomerReviewStatus(array $params)
    {
        $product_id = $params['form_data']['id'];

        $buckarooIdin = $params['form_data']['buckaroo_idin']['buckaroo_idin'];

        $sql = 'UPDATE ' . _DB_PREFIX_ . 'product SET buckaroo_idin = ' . (int) $buckarooIdin . ' WHERE id_product = ' . (int) $product_id;

        try {
            Db::getInstance()->execute($sql);
        } catch (Exception $e) {
            $this->logger->logError('Buckaroo3::updateCustomerReviewStatus - ' . $e->getMessage());
        }
    }
}