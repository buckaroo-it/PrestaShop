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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/config.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/api/paymentmethods/responsefactory.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/library/logger.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/common.php';

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Buckaroo3 extends PaymentModule
{
    public $context;
    public function __construct()
    {
        $this->name                   = 'buckaroo3';
        $this->tab                    = 'payments_gateways';
        $this->version                = '3.3.11';
        $this->author                 = 'Buckaroo';
        $this->need_instance          = 1;
        $this->module_key             = '8d2a2f65a77a8021da5d5ffccc9bbd2b';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.7');

        parent::__construct();

        $this->displayName = $this->l('Buckaroo Payments') . ' (v ' . $this->version . ')';
        $this->description = $this->l('Buckaroo Payment module. Compatible with PrestaShop version 1.6.x + 1.7.x');

        $this->confirmUninstall = $this->l('Are you sure you want to delete Buckaroo Payments module?');
        $this->tpl_folder       = 'buckaroo3';

        $response = ResponseFactory::getResponse();
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
                        $this->displayName = $this->l('Buckaroo Payments (v 3.3.11)');
                    }
                }
            }
        }

        if (!Configuration::get('BUCKAROO_MERCHANT_KEY') ||
            !Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT') ||
            !Configuration::get('BUCKAROO_ORDER_STATE_SUCCESS') ||
            !Configuration::get('BUCKAROO_ORDER_STATE_FAILED')
        ) {
            $this->warning = $this->l('You should configurate Buckaroo module before use!');
        }

        $translations   = array();
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

    public function hookDisplayBackOfficeTop($params)
    {
        return $this->display(__FILE__, 'views/templates/hook/buckaroolog-quicklinks.tpl');
    }

    public function hookDisplayAdminOrderMainBottom($params)
    {
        $cookie        = new Cookie('ps');
        $order         = new Order($params["id_order"]);
        $payments      = $order->getOrderPaymentCollection();
        $messages      = '';
        $messageStatus = 0;
        if (!empty($cookie->refundMessage)) {
            $messages              = $cookie->refundMessage;
            $messageStatus         = $cookie->refundStatus;
            $cookie->refundMessage = '';
        }
        $paymentInfo = array();
        $refunded    = array();
        foreach ($payments as $payment) {
            /* @var $payment OrderPaymentCore */
            $sql_order = 'SELECT `original_transaction`
                FROM `' . _DB_PREFIX_ . 'buckaroo_transactions`
                WHERE `transaction_id` = \'' . pSQL($payment->transaction_id) . '\'';
            $result_order = Db::getInstance()->getRow($sql_order);
            if (!empty($result_order["original_transaction"])) {
                if (empty($refunded[$result_order["original_transaction"]])) {
                    $refunded[$result_order["original_transaction"]] = 0;
                }
                $refunded[$result_order["original_transaction"]] += $payment->amount;
            }
        }
        foreach ($payments as $payment) {
            /* @var $payment OrderPaymentCore */
            $paymentInfo[$payment->id] = array(
                "refunded" => (!empty($refunded[$payment->transaction_id])) ? $refunded[$payment->transaction_id] : 0,
            );
        }

        $cart        = new Cart($order->id_cart);
        $buckarooFee = $this->getBuckarooFeeByCartId($cart->id);
        $currency    = new Currency((int) $order->id_currency);
        $buckarooFee = Tools::displayPrice($buckarooFee, $currency, false);

        $this->smarty->assign(
            array(
                'order'         => $order,
                'payments'      => $payments,
                'messages'      => $messages,
                'paymentInfo'   => $paymentInfo,
                'messageStatus' => $messageStatus,
                'buckarooFee'   => $buckarooFee,
                'refundLink'    => $this->context->link->getAdminLink('AdminRefund', true)
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
        $sql = "UPDATE `" . _DB_PREFIX_ . "orders` SET total_paid_tax_incl = '" . ($order->total_paid) .
            "' WHERE id_cart = '" . $cart->id . "'";
        Db::getInstance()->execute($sql);

        $currency    = new Currency((int) $order->id_currency);
        $buckarooFee = Tools::displayPrice($buckarooFee, $currency, false);

        $return = '<script>
        document.addEventListener("DOMContentLoaded", function(){
            $(".total-value").before($("<tr><td>Buckaroo Fee</td><td>' . $buckarooFee . '</td></tr>"))
            });
        </script>';
        return $return;
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCss($this->_path . 'views/css/tab.css');
    }

    public function addBuckarooIdin()
    {
        Db::getInstance()->query('SHOW COLUMNS FROM `'._DB_PREFIX_.'customer` LIKE "buckaroo_idin_%"');
        if (Db::getInstance()->NumRows() == 0) {
            Db::getInstance()->execute("ALTER TABLE `" . _DB_PREFIX_ . "customer` 
            ADD buckaroo_idin_consumerbin VARCHAR(255) NULL, ADD buckaroo_idin_iseighteenorolder VARCHAR(255) NULL;");
        }

        Db::getInstance()->query('SHOW COLUMNS FROM `'._DB_PREFIX_.'product` LIKE "buckaroo_idin"');
        if (Db::getInstance()->NumRows() == 0) {
            Db::getInstance()->execute("ALTER TABLE `" . _DB_PREFIX_ . "product` 
            ADD buckaroo_idin TINYINT(1) NULL;");
        }
    }

    public function createTransactionTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'buckaroo_transactions`(
    `id_buckaroo_transaction` int(10) unsigned NOT NULL auto_increment,
    `transaction_id` varchar(255) NOT NULL default \'\',
    `original_transaction` varchar(255) NOT NULL default \'\',
    PRIMARY KEY (`id_buckaroo_transaction`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        return Db::getInstance()->execute($sql);
    }

// this also works, and is more future-proof
    public function install()
    {
        if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook(
            'header'
        ) || !$this->registerHook('paymentReturn') || !$this->registerHook('backOfficeHeader')
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('displayBackOfficeTop')
            || !$this->registerHook('displayAdminOrderMainBottom')
            || !$this->registerHook('displayOrderConfirmation')
            || !$this->installModuleTab('AdminBuckaroolog', array(1 => 'Buckaroo error log'), 0)
            || !$this->installModuleTab('AdminRefund', array(1 => 'Buckaroo Refunds'), -1)
            || !$this->createTransactionTable()
            || !$this->registerHook('actionEmailSendBefore')
            || !$this->registerHook('displayPDFInvoice')
        ) {
            return false;
        }
        $this->registerHook('displayBackOfficeHeader');

        $this->registerHook('displayBeforeCarrier');
        $this->registerHook('actionAdminCustomersListingFieldsModifier');
        $this->registerHook('displayAdminProductsMainStepLeftColumnMiddle');
        $this->registerHook('displayProductExtraContent');
        $this->addBuckarooIdin();

        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }
        Configuration::updateValue('BUCKAROO_TEST', '1');
        Configuration::updateValue('BUCKAROO_MERCHANT_KEY', '');
        Configuration::updateValue('BUCKAROO_SECRET_KEY', '');
        Configuration::updateValue('BUCKAROO_CERTIFICATE', '');
        Configuration::updateValue('BUCKAROO_CERTIFICATE_FILE', '');
        Configuration::updateValue('BUCKAROO_CERTIFICATE_THUMBPRINT', '');
        Configuration::updateValue('BUCKAROO_TRANSACTION_LABEL', '');
        Configuration::updateValue('BUCKAROO_TRANSACTION_FEE', '');
        Configuration::updateValue('BUCKAROO_PAYPAL_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_PAYPAL_TEST', '1');
        Configuration::updateValue('BUCKAROO_PAYPAL_LABEL', '');
        Configuration::updateValue('BUCKAROO_BUCKAROOPAYPAL_FEE', '');
        Configuration::updateValue('BUCKAROO_EMPAYMENT_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_EMPAYMENT_TEST', '1');
        Configuration::updateValue('BUCKAROO_EMPAYMENT_LABEL', '');
        Configuration::updateValue('BUCKAROO_EMPAYMENT_FEE', '');
        Configuration::updateValue('BUCKAROO_DD_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_DD_TEST', '1');
        Configuration::updateValue('BUCKAROO_DD_LABEL', '');
        Configuration::updateValue('BUCKAROO_DD_FEE', '');
        Configuration::updateValue('BUCKAROO_DD_USECREDITMANAGMENT', 'No');
        Configuration::updateValue('BUCKAROO_DD_INVOICEDELAY', '0');
        Configuration::updateValue('BUCKAROO_DD_DATEDUE', '0');
        Configuration::updateValue('BUCKAROO_DD_MAXREMINDERLEVEL', '4');
        Configuration::updateValue('BUCKAROO_IDEAL_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_IDEAL_TEST', '1');
        Configuration::updateValue('BUCKAROO_IDEAL_LABEL', '');
        Configuration::updateValue('BUCKAROO_IDEAL_FEE', '');
        Configuration::updateValue('BUCKAROO_GIROPAY_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_GIROPAY_TEST', '1');
        Configuration::updateValue('BUCKAROO_GIROPAY_LABEL', '');
        Configuration::updateValue('BUCKAROO_GIROPAY_FEE', '');
        Configuration::updateValue('BUCKAROO_KBC_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_KBC_TEST', '1');
        Configuration::updateValue('BUCKAROO_KBC_LABEL', '');
        Configuration::updateValue('BUCKAROO_KBC_FEE', '');
        Configuration::updateValue('BUCKAROO_MISTERCASH_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_MISTERCASH_TEST', '1');
        Configuration::updateValue('BUCKAROO_MISTERCASH_LABEL', '');
        Configuration::updateValue('BUCKAROO_MISTERCASH_FEE', '');
        Configuration::updateValue('BUCKAROO_GIFTCARD_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_GIFTCARD_TEST', '1');
        Configuration::updateValue('BUCKAROO_GIFTCARD_LABEL', '');
        Configuration::updateValue('BUCKAROO_GIFTCARD_FEE', '');
        Configuration::updateValue('BUCKAROO_CREDITCARD_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_CREDITCARD_TEST', '1');
        Configuration::updateValue('BUCKAROO_CREDITCARD_LABEL', '');
        Configuration::updateValue('BUCKAROO_CREDITCARD_FEE', '');
        Configuration::updateValue('BUCKAROO_SOFORTBANKING_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_SOFORTBANKING_TEST', '1');
        Configuration::updateValue('BUCKAROO_SOFORTBANKING_LABEL', '');
        Configuration::updateValue('BUCKAROO_SOFORTBANKING_FEE', '');
        Configuration::updateValue('BUCKAROO_BELFIUS_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_BELFIUS_TEST', '1');
        Configuration::updateValue('BUCKAROO_BELFIUS_LABEL', '');
        Configuration::updateValue('BUCKAROO_BELFIUS_FEE', '');
        Configuration::updateValue('BUCKAROO_TRANSFER_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_TRANSFER_TEST', '1');
        Configuration::updateValue('BUCKAROO_TRANSFER_LABEL', '');
        Configuration::updateValue('BUCKAROO_TRANSFER_FEE', '');
        Configuration::updateValue('BUCKAROO_TRANSFER_DATEDUE', '14');
        Configuration::updateValue('BUCKAROO_TRANSFER_SENDMAIL', '0');

        Configuration::updateValue('BUCKAROO_AFTERPAY_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_AFTERPAY_TEST', '1');
        Configuration::updateValue('BUCKAROO_AFTERPAY_LABEL', '');
        Configuration::updateValue('BUCKAROO_AFTERPAY_FEE', '');
        Configuration::updateValue('BUCKAROO_AFTERPAY_DEFAULT_VAT', '2');
        Configuration::updateValue('BUCKAROO_AFTERPAY_WRAPPING_VAT', '2');
        Configuration::updateValue('BUCKAROO_AFTERPAY_TAXRATE', serialize(array()));
        Configuration::updateValue('BUCKAROO_AFTERPAY_BUSINESS', 'B2C');

        Configuration::updateValue('BUCKAROO_KLARNA_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_KLARNA_TEST', '1');
        Configuration::updateValue('BUCKAROO_KLARNA_LABEL', '');
        Configuration::updateValue('BUCKAROO_KLARNA_FEE', '');
        Configuration::updateValue('BUCKAROO_KLARNA_DEFAULT_VAT', '2');
        Configuration::updateValue('BUCKAROO_KLARNA_WRAPPING_VAT', '2');
        Configuration::updateValue('BUCKAROO_KLARNA_TAXRATE', serialize(array()));

        Configuration::updateValue('BUCKAROO_APPLEPAY_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_APPLEPAY_TEST', '1');
        Configuration::updateValue('BUCKAROO_APPLEPAY_LABEL', '');
        Configuration::updateValue('BUCKAROO_APPLEPAY_FEE', '');

        $states = OrderState::getOrderStates((int) Configuration::get('PS_LANG_DEFAULT'));

        $currentStates = array();
        foreach ($states as $state) {
            $state                                 = (object) $state;
            $currentStates[$state->id_order_state] = $state->name;
        }

        $state_id = 0;
        if (($state_id = array_search($this->l('Awaiting for Remote payment'), $currentStates)) === false) {
            // Add the custom order state
            $defaultOrderState       = new OrderState();
            $defaultOrderState->name = array(
                Configuration::get('PS_LANG_DEFAULT') => $this->l(
                    'Awaiting for Remote payment'
                ),
            );
            $defaultOrderState->module_name = $this->name;
            $defaultOrderState->send_mail   = 0;
            $defaultOrderState->template    = '';
            $defaultOrderState->invoice     = 0;
            $defaultOrderState->color       = '#FFF000';
            $defaultOrderState->unremovable = false;
            $defaultOrderState->logable     = 0;
            if ($defaultOrderState->add()) {
                $source      = dirname(__FILE__) . '/logo.gif';
                $destination = dirname(__FILE__) . '/../../img/os/' . (int) $defaultOrderState->id . '.gif';
                if (!file_exists($destination)) {
                    copy($source, $destination);
                }
            }
        } else {
            $defaultOrderState     = new stdClass;
            $defaultOrderState->id = $state_id;
        }

        Configuration::updateValue('BUCKAROO_ORDER_STATE_DEFAULT', $defaultOrderState->id);
        Configuration::updateValue('BUCKAROO_ORDER_STATE_SUCCESS', Configuration::get('PS_OS_PAYMENT'));
        Configuration::updateValue('BUCKAROO_ORDER_STATE_FAILED', Configuration::get('PS_OS_CANCELED'));
        $this->addBuckarooFeeTable();

        //override
        $this->overrideClasses();

        return true;
    }

    protected function overrideClasses()
    {
        copy(_PS_ROOT_DIR_ . "/modules/buckaroo3/classes/Mail.php", _PS_ROOT_DIR_ . "/override/classes/Mail.php");
    }

    protected function addBuckarooFeeTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "buckaroo_fee`
        ( `id` INT NOT NULL AUTO_INCREMENT , `reference` TEXT NOT NULL , `id_cart` TEXT NOT NULL , `buckaroo_fee` FLOAT,
         `currency` TEXT NOT NULL ,  PRIMARY KEY (id) )";

        Db::getInstance()->execute($sql);
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        $this->uninstallModuleTab('AdminBuckaroolog');
        $this->uninstallModuleTab('AdminRefund');
        $this->unregisterHook('displayBackOfficeHeader');
        $this->unregisterHook('displayAdminOrderMainBottom');
        $this->unregisterHook('displayOrderConfirmation');
        $this->unregisterHook('actionEmailSendBefore');
        $this->unregisterHook('displayPDFInvoice');

        // Clean configuration table
        Configuration::deleteByName('BUCKAROO_TEST');
        Configuration::deleteByName('BUCKAROO_MERCHANT_KEY');
        Configuration::deleteByName('BUCKAROO_SECRET_KEY');
        Configuration::deleteByName('BUCKAROO_CERTIFICATE');
        Configuration::deleteByName('BUCKAROO_CERTIFICATE_FILE');
        Configuration::deleteByName('BUCKAROO_CERTIFICATE_THUMBPRINT');
        Configuration::deleteByName('BUCKAROO_TRANSACTION_LABEL');
        Configuration::deleteByName('BUCKAROO_TRANSACTION_FEE');
        //paypal
        Configuration::deleteByName('BUCKAROO_PAYPAL_ENABLED');
        Configuration::deleteByName('BUCKAROO_PAYPAL_TEST');
        //empayment
        Configuration::deleteByName('BUCKAROO_EMPAYMENT_ENABLED');
        Configuration::deleteByName('BUCKAROO_EMPAYMENT_TEST');
        Configuration::deleteByName('BUCKAROO_EMPAYMENT_LABEL');
        Configuration::deleteByName('BUCKAROO_EMPAYMENT_FEE');
        //directdebit
        Configuration::deleteByName('BUCKAROO_DD_ENABLED');
        Configuration::deleteByName('BUCKAROO_DD_TEST');
        Configuration::deleteByName('BUCKAROO_DD_LABEL');
        Configuration::deleteByName('BUCKAROO_DD_FEE');
        Configuration::deleteByName('BUCKAROO_DD_USECREDITMANAGMENT');
        Configuration::deleteByName('BUCKAROO_DD_INVOICEDELAY');
        Configuration::deleteByName('BUCKAROO_DD_DATEDUE');
        Configuration::deleteByName('BUCKAROO_DD_MAXREMINDERLEVEL');
        //sepadirectdebit
        Configuration::deleteByName('BUCKAROO_SDD_ENABLED');
        Configuration::deleteByName('BUCKAROO_SDD_TEST');
        Configuration::deleteByName('BUCKAROO_SDD_LABEL');
        Configuration::deleteByName('BUCKAROO_SDD_FEE');

        Configuration::deleteByName('BUCKAROO_IDEAL_ENABLED');
        Configuration::deleteByName('BUCKAROO_IDEAL_TEST');
        Configuration::deleteByName('BUCKAROO_IDEAL_LABEL');
        Configuration::deleteByName('BUCKAROO_IDEAL_FEE');

        Configuration::deleteByName('BUCKAROO_GIROPAY_ENABLED');
        Configuration::deleteByName('BUCKAROO_GIROPAY_TEST');
        Configuration::deleteByName('BUCKAROO_GIROPAY_LABEL');
        Configuration::deleteByName('BUCKAROO_GIROPAY_FEE');

        Configuration::deleteByName('BUCKAROO_KBC_ENABLED');
        Configuration::deleteByName('BUCKAROO_KBC_TEST');
        Configuration::deleteByName('BUCKAROO_KBC_LABEL');
        Configuration::deleteByName('BUCKAROO_KBC_FEE');

        Configuration::deleteByName('BUCKAROO_MISTERCASH_ENABLED');
        Configuration::deleteByName('BUCKAROO_MISTERCASH_TEST');
        Configuration::deleteByName('BUCKAROO_MISTERCASH_LABEL');
        Configuration::deleteByName('BUCKAROO_MISTERCASH_FEE');

        Configuration::deleteByName('BUCKAROO_GIFTCARD_ENABLED');
        Configuration::deleteByName('BUCKAROO_GIFTCARD_TEST');
        Configuration::deleteByName('BUCKAROO_GIFTCARD_LABEL');
        Configuration::deleteByName('BUCKAROO_GIFTCARD_FEE');

        Configuration::deleteByName('BUCKAROO_CREDITCARD_ENABLED');
        Configuration::deleteByName('BUCKAROO_CREDITCARD_TEST');
        Configuration::deleteByName('BUCKAROO_CREDITCARD_LABEL');
        Configuration::deleteByName('BUCKAROO_CREDITCARD_FEE');

        Configuration::deleteByName('BUCKAROO_SOFORTBANKING_ENABLED');
        Configuration::deleteByName('BUCKAROO_SOFORTBANKING_TEST');
        Configuration::deleteByName('BUCKAROO_SOFORTBANKING_LABEL');
        Configuration::deleteByName('BUCKAROO_SOFORTBANKING_FEE');

        Configuration::deleteByName('BUCKAROO_BELFIUS_ENABLED');
        Configuration::deleteByName('BUCKAROO_BELFIUS_TEST');
        Configuration::deleteByName('BUCKAROO_BELFIUS_LABEL');
        Configuration::deleteByName('BUCKAROO_BELFIUS_FEE');

        Configuration::deleteByName('BUCKAROO_AFTERPAY_ENABLED');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_TEST');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_LABEL');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_FEE');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_DEFAULT_VAT');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_WRAPPING_VAT');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_TAXRATE');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_BUSINESS');

        Configuration::deleteByName('BUCKAROO_KLARNA_ENABLED');
        Configuration::deleteByName('BUCKAROO_KLARNA_TEST');
        Configuration::deleteByName('BUCKAROO_KLARNA_LABEL');
        Configuration::deleteByName('BUCKAROO_KLARNA_FEE');
        Configuration::deleteByName('BUCKAROO_KLARNA_DEFAULT_VAT');
        Configuration::deleteByName('BUCKAROO_KLARNA_WRAPPING_VAT');
        Configuration::deleteByName('BUCKAROO_KLARNA_TAXRATE');

        Configuration::deleteByName('BUCKAROO_ORDER_STATE_DEFAULT');
        Configuration::deleteByName('BUCKAROO_ORDER_STATE_SUCCESS');
        Configuration::deleteByName('BUCKAROO_ORDER_STATE_FAILED');

        Configuration::deleteByName('BUCKAROO_APPLEPAY_ENABLED');
        Configuration::deleteByName('BUCKAROO_APPLEPAY_TEST');
        Configuration::deleteByName('BUCKAROO_APPLEPAY_LABEL');
        Configuration::deleteByName('BUCKAROO_APPLEPAY_FEE');

        return true;
    }

    public function getContent()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/buckaroo3.admin.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/buckaroo.admin.js');
        include_once _PS_MODULE_DIR_ . '/' . $this->name . '/buckaroo3_admin.php';
        $buckaroo_admin = new Buckaroo3Admin($this);
        return $buckaroo_admin->postProcess() . $buckaroo_admin->displayForm();
    }

    public function hookBackOfficeHeader()
    {
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        $cookie            = new Cookie('ps');
        $cart              = new Cart($params['cookie']->__get('id_cart'));
        $customer          = new Customer($cart->id_customer);
        $cookie_id_lang    = (int) ($cookie->id_lang);
        $id_lang           = $cookie_id_lang ? $cookie_id_lang : (int) (Configuration::get('PS_LANG_DEFAULT'));
        $addresses         = $customer->getAddresses($id_lang);
        $company           = '';
        $vat               = '';
        $firstNameBilling  = '';
        $firstNameShipping = '';
        $lastNameBilling   = '';
        $lastNameShipping  = '';
        $phone             = '';
        $phone_mobile      = '';
        foreach ($addresses as $address) {
            if ($address['id_address'] == $cart->id_address_delivery) {
                $phone             = $address['phone'];
                $phone_mobile      = $address['phone_mobile'];
                $firstNameShipping = $address["firstname"];
                $lastNameShipping  = $address["lastname"];
            }
            if ($address['id_address'] == $cart->id_address_invoice) {
                $company              = $address['company'];
                $vat                  = $address['vat_number'];
                $phone_billing        = $address['phone'];
                $phone_mobile_billing = $address['phone_mobile'];
                $firstNameBilling     = $address["firstname"];
                $lastNameBilling      = $address["lastname"];
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
        require_once dirname(__FILE__) . '/config.php';

        $this->context->smarty->assign(
            array(
                'address_differ'          => $address_differ,
                'this_path'               => $this->_path,
                'customer_gender'         => $customer->id_gender,
                'customer_name'           => $customer->firstname . ' ' . $customer->lastname,
                'customer_email'          => $customer->email,
                'customer_birthday'       => explode('-', $customer->birthday),
                'customer_company'        => $company,
                'customer_vat'            => $vat,
                'phone'                   => $phone,
                'phone_mobile'            => $phone_mobile,
                'phone_afterpay_shipping' => $phone_afterpay_shipping,
                'phone_afterpay_billing'  => $phone_afterpay_billing,
                'total'                   => $cart->getOrderTotal(true, 3),
                'country'                 => Country::getIsoById(Tools::getCountry()),
            )
        );

        $payment_options = [];
        libxml_use_internal_errors(true);
        if (Config::get('BUCKAROO_IDEAL_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('IDEAL', 'Pay by iDeal'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'ideal']))
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_ideal.tpl'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_ideal.png?v');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_PAYPAL_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('BUCKAROOPAYPAL', 'Pay by PayPal'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'buckaroopaypal']))
                ->setInputs($this->getBuckarooFeeInputs('BUCKAROOPAYPAL'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_paypal.png?v');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_SDD_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('SDD', 'Pay by SEPA Direct Debit'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'sepadirectdebit'])) //phpcs:ignore
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_sepadirectdebit.tpl')) //phpcs:ignore
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_sepa_dd.png?v'); //phpcs:ignore
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_GIROPAY_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('GIROPAY', 'Pay by GiroPay'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'giropay']))
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_giropay.tpl'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_giropay.png?v');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_KBC_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('KBC', 'Pay by KBC'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'kbc']))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_kbc.png?v');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_MISTERCASH_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('MISTERCASH', 'Pay by  Bancontact / Mister Cash'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'bancontactmrcash'])) //phpcs:ignore
                ->setInputs($this->getBuckarooFeeInputs('MISTERCASH'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_mistercash.png?vv'); //phpcs:ignore
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_GIFTCARD_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('GIFTCARD', 'Pay by Giftcards'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'giftcard']))
                ->setInputs($this->getBuckarooFeeInputs('GIFTCARD'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_giftcards.png?v');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_CREDITCARD_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('CREDITCARD', 'Pay by Creditcards'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'creditcard']))
                ->setInputs($this->getBuckarooFeeInputs('CREDITCARD'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_cc.png?v');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_SOFORTBANKING_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('SOFORTBANKING', 'Pay by Sofortbanking'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'sofortueberweisung'])) //phpcs:ignore
                ->setInputs($this->getBuckarooFeeInputs('SOFORTBANKING'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_sofort.png?v'); //phpcs:ignore
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_BELFIUS_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('BELFIUS', 'Pay by Belfius'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'belfius'])) //phpcs:ignore
                ->setInputs($this->getBuckarooFeeInputs('BELFIUS'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_belfius.png?v'); //phpcs:ignore
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_TRANSFER_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('TRANSFER', 'Pay by Bank Transfer'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'transfer']))
                ->setInputs($this->getBuckarooFeeInputs('TRANSFER'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_transfer.png?vv1');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_AFTERPAY_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('AFTERPAY', 'Afterpay'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'afterpay'])) //phpcs:ignore
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_afterpay.tpl')) //phpcs:ignore
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_afterpay.png?vv'); //phpcs:ignore
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_KLARNA_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('KLARNA', 'Klarna: Pay later'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'klarna'])) //phpcs:ignore
                ->setInputs($this->getBuckarooFeeInputs('KLARNA'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_klarna.png?v'); //phpcs:ignore
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_APPLEPAY_ENABLED')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('APPLEPAY', 'Apple Pay'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'applepay'])) //phpcs:ignore
                ->setInputs($this->getBuckarooFeeInputs('APPLEPAY'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_applepay.png?v'); //phpcs:ignore
            $payment_options[] = $newOption;
        }
        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        if (Tools::getValue("responce_received")) {
            switch (Tools::getValue("responce_received")) {
                case 'transfer':
                    $order   = new Order(Tools::getValue('id_order'));
                    $price   = $order->getOrdersTotalPaid();
                    $message = $this->context->cookie->HtmlText;
                    $this->context->smarty->assign(
                        array(
                            'is_guest' => (($this->context->customer->is_guest)
                                || $this->context->customer->id == false),
                            'order'    => $order,
                            'message'  => $message,
                            'price'    => Tools::displayPrice($price, $this->context->currency->id),
                        )
                    );
                    return $this->display(__FILE__, 'payment_return_redirectsuccess.tpl');
                default:
                    $order = new Order(Tools::getValue('id_order'));
                    $price = $order->getOrdersTotalPaid();
                    $this->context->smarty->assign(
                        array(
                            'is_guest' => (($this->context->customer->is_guest)
                                || $this->context->customer->id == false),
                            'order'    => $order,
                            'price'    => Tools::displayPrice($price, $this->context->currency->id),
                        )
                    );
                    return $this->display(__FILE__, 'payment_return_success.tpl');
            }
        } else {
            if (Tools::getValue("id_order") && Tools::getValue("success")) {
                $order = new Order(Tools::getValue("id_order"));
                if ($order) {
                    $price = $order->getOrdersTotalPaid();
                    $this->context->smarty->assign(
                        array(
                            'is_guest' => (($this->context->customer->is_guest) || $this->context->customer->id == false), //phpcs:ignore
                            'order'    => $order,
                            'price'    => Tools::displayPrice($price, $this->context->currency->id),
                        )
                    );
                    return $this->display(__FILE__, 'payment_return_success.tpl');
                } else {
                    Tools::redirect('index.php?fc=module&module=buckaroo3&controller=error');
                    exit();
                }
            } else {
                Tools::redirect('index.php?fc=module&module=buckaroo3&controller=error');
                exit();
            }
        }
    }

    public function installModuleTab($tabClass, $tabName, $idTabParent)
    {
        $tab             = new Tab();
        $tab->name       = $tabName;
        $tab->class_name = $tabClass;
        $tab->module     = $this->name;
        $tab->id_parent  = $idTabParent;

        if (!$tab->save()) {
            return false;
        }
        return true;
    }

    public function uninstallModuleTab($tabClass)
    {
        $idTab = Tab::getIdFromClassName($tabClass);
        if ($idTab != 0) {
            $tab = new Tab($idTab);
            $tab->delete();
            return true;
        }
        return false;
    }

    public function hookDisplayHeader()
    {
        Media::addJsDef([
            'buckarooAjaxUrl' => $this->context->link->getModuleLink('buckaroo3', 'ajax'),
            'buckarooFees'    => $this->getBuckarooFees(),
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
            case 'buckaroopaypal':
                $payment_method_tr = $this->l('PayPal');
                break;
            case 'SepaDirectDebit':
            case 'sepadirectdebit':
                $payment_method_tr = $this->l('Sepa Direct Debit');
                break;
            case 'ideal':
                $payment_method_tr = $this->l('iDeal');
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
                $payment_method_tr = $this->l('AfterPay');
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
            default:
                $payment_method_tr = $this->l($payment_method);
                break;
        }
        return $payment_method_tr;
    }

    public static function cleanUpPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (Tools::substr($phone, 0, 3) == '316' || Tools::substr($phone, 0, 5) == '00316' || Tools::substr(
            $phone,
            0,
            6
        ) == '003106' || Tools::substr($phone, 0, 2) == '06'
        ) {
            if (Tools::substr($phone, 0, 6) == '003106') {
                $phone = substr_replace($phone, '00316', 0, 6);
            }
            $response = array('type' => 'mobile', 'phone' => $phone);
        } else {
            $response = array('type' => 'landline', 'phone' => $phone);
        }

        return $response;
    }

    public function getBuckarooLabel($method, $label)
    {
        if (Config::get('BUCKAROO_' . $method . '_LABEL')) {
            $label = Config::get('BUCKAROO_' . $method . '_LABEL');
        }

        if (Config::get('BUCKAROO_' . $method . '_FEE')) {
            $buckarooFee = Config::get('BUCKAROO_' . $method . '_FEE');
            if ($buckarooFee > 0) {
                $label .= ' + ' . Tools::displayPrice($buckarooFee, $this->context->currency->id);
            }
        }
        return $this->l($label);
    }

    public function getBuckarooFeeByCartId($id_cart)
    {
        $sql = 'SELECT buckaroo_fee FROM ' . _DB_PREFIX_ . 'buckaroo_fee where id_cart = ' . (int) ($id_cart);
        return Db::getInstance()->getValue($sql);
    }

    public function getBuckarooFees()
    {
        $methods = [
            'IDEAL',
            'BUCKAROOPAYPAL',
            'SDD',
            'GIROPAY',
            'KBC',
            'MISTERCASH',
            'GIFTCARD',
            'CREDITCARD',
            'SOFORTBANKING',
            'BELFIUS',
            'TRANSFER',
            'AFTERPAY',
            'KLARNA',
            'APPLEPAY'
        ];
        $result  = [];
        foreach ($methods as $method) {
            if (Config::get('BUCKAROO_' . $method . '_FEE')) {
                $buckarooFee = Config::get('BUCKAROO_' . $method . '_FEE');
                if ($buckarooFee > 0) {
                    $result[$method] = [
                        "buckarooFee"        => $buckarooFee,
                        "buckarooFeeDisplay" => Tools::displayPrice($buckarooFee),
                    ];
                }
            }
        }
        return $result;
    }

    public function getBuckarooFeeInputs($method)
    {
        $result = [];
        if (Config::get('BUCKAROO_' . $method . '_FEE')) {
            $buckarooFee = Config::get('BUCKAROO_' . $method . '_FEE');
            if ($buckarooFee > 0) {
                $result = [
                    [
                        'type'  => 'hidden',
                        'name'  => "payment-fee-price",
                        'value' => $buckarooFee,
                    ],
                    [
                        'type'  => 'hidden',
                        'name'  => "payment-fee-price-display",
                        'value' => Tools::displayPrice($buckarooFee),
                    ],
                ];
            }
        }
        return $result;
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

        if ($params['template'] === 'order_conf' ||
            $params['template'] === 'account' ||
            $params['template'] === 'backoffice_order' ||
            $params['template'] === 'contact_form' ||
            $params['template'] === 'credit_slip' ||
            $params['template'] === 'in_transit' ||
            $params['template'] === 'order_changed' ||
            $params['template'] === 'order_merchant_comment' ||
            $params['template'] === 'order_return_state' ||
            $params['template'] === 'cheque' ||
            $params['template'] === 'payment' ||
            $params['template'] === 'preparation' ||
            $params['template'] === 'shipped' ||
            $params['template'] === 'order_canceled' ||
            $params['template'] === 'payment_error' ||
            $params['template'] === 'outofstock' ||
            $params['template'] === 'bankwire' ||
            $params['template'] === 'refund') {
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

    public function hookDisplayBeforeCarrier(array $params)
    {
        $cart = isset($params['cart']) ? $params['cart'] : null;
        if ($cart === null || !$cart->id_address_delivery) {
            return '';
        }

        $address = new Address($cart->id_address_delivery);
        $country = new Country($address->id_country);
        $context = $this->context;

        $this->smarty->assign(array(
            'buckaroo_idin_test' => Configuration::get('BUCKAROO_IDIN_TEST'),
            'this_path'          => _MODULE_DIR_ . $this->tpl_folder . '/',
            'cart'               => $cart,
            'to_country'         => $country->iso_code,
            'to_postal_code'     => $address->postcode,
            'language'           => $context->language->language_code,
        ));

        if ($this->isIdinBoxShow($cart)) {
            return $this->display(__FILE__, 'views/templates/hook/idin.tpl');
        }
    }

    public function isIdinBoxShow($cart)
    {
        if ($this->isIdinCheckout($cart)) {
            if ($this->isCustomerIdinValid($cart)) {
                return false;
            }
            return true;
        }
        return false;
    }

    public function isIdinProductBoxShow($params)
    {
        $buckaroo_idin_category = array();
        $tmp_arr                = Configuration::get('BUCKAROO_IDIN_CATEGORY');
        if (!empty($tmp_arr)) {
            $c = unserialize($tmp_arr);
            if (is_array($c)) {
                $buckaroo_idin_category = array_flip($c);
            }
        }

        if (Configuration::get('BUCKAROO_IDIN_ENABLED') == '1') {
            switch (Configuration::get('BUCKAROO_IDIN_MODE')) {
                case 1:
                    if (isset($params['product']->buckaroo_idin) && $params['product']->buckaroo_idin == 1) {
                        return true;
                    }
                    break;
                case 2:
                    if (isset($params['product']->id_category_default)
                        && isset($buckaroo_idin_category[$params['product']->id_category_default])
                    ) {
                        return true;
                    }
                    break;
                default:
                    return true;
            }
        }
        return false;
    }

    public function isIdinCheckout($cart)
    {
        $buckaroo_idin_category = array();
        $tmp_arr                = Configuration::get('BUCKAROO_IDIN_CATEGORY');
        if (!empty($tmp_arr)) {
            $c = unserialize($tmp_arr);
            if (is_array($c)) {
                $buckaroo_idin_category = array_flip($c);
            }
        }

        $cart_products = $cart->getProducts(true);

        if (Configuration::get('BUCKAROO_IDIN_ENABLED') == '1') {
            switch (Configuration::get('BUCKAROO_IDIN_MODE')) {
                case 1:
                    foreach ($cart_products as $value) {
                        $product   = new Product($value['id_product']);
                        if (isset($product->buckaroo_idin) && $product->buckaroo_idin == 1) {
                            return true;
                        }
                    }
                    break;
                case 2:
                    foreach ($cart_products as $product) {
                        if (isset($product['id_category_default'])
                            && isset($buckaroo_idin_category[$product['id_category_default']])
                        ) {
                            return true;
                        }
                    }
                    break;
                default:
                    return true;
            }
        }
        return false;
    }

    public function isCustomerIdinValid($cart)
    {
        $id_customer = $cart->id_customer;
        $query       = 'SELECT c.`buckaroo_idin_iseighteenorolder`'
        . ' FROM `' . _DB_PREFIX_ . 'customer` c '
        . ' WHERE c.id_customer = ' . (int) $id_customer;
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query) == 'True' ? true : false;
    }

    public function hookActionAdminCustomersListingFieldsModifier($params)
    {
        $params['fields']['buckaroo_idin_consumerbin'] = array(
            'title' => $this->l('iDIN Consumerbin'),
            'align' => 'center',
        );

        $params['fields']['buckaroo_idin_iseighteenorolder'] = array(
            'title' => $this->l('iDIN isEighteenOrOlder'),
            'align' => 'center',
        );
    }

    public function hookDisplayProductExtraContent($params)
    {
        if ($this->isIdinProductBoxShow($params)) {
            $content = $this->display(__FILE__, 'views/templates/hook/idin_box.tpl', array(
                'this_path' => _MODULE_DIR_ . $this->tpl_folder . '/',
            ));

            $array = [];
            $array[] = (new PrestaShop\PrestaShop\Core\Product\ProductExtraContent())
                ->setTitle('iDIN Info')
                ->setContent($content);

            return $array;
        }
    }

    public function hookDisplayAdminProductsMainStepLeftColumnMiddle($params)
    {
        $product   = new Product($params['id_product']);
        $languages = Language::getLanguages(false);
        $this->context->smarty->assign(array(
            'buckaroo_idin'    => $product->buckaroo_idin,
            'languages'        => $languages,
            'default_language' => $this->context->employee->id_lang,
        ));

        return $this->display(__FILE__, 'views/templates/hook/product_fileds.tpl');
    }
}
