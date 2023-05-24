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
require_once _PS_ROOT_DIR_ . '/modules/buckaroo3/vendor/autoload.php';
require_once dirname(__FILE__) . '/config.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/api/paymentmethods/responsefactory.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/library/logger.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/common.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/api/paymentmethods/afterpay/afterpay.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/api/paymentmethods/billink/billink.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/classes/IssuersIdeal.php';

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Buckaroo3 extends PaymentModule
{
    public $context;
    public function __construct()
    {
        $this->name                   = 'buckaroo3';
        $this->tab                    = 'payments_gateways';
        $this->version                = '3.4.0';
        $this->author                 = 'Buckaroo';
        $this->need_instance          = 1;
        $this->bootstrap              = true;
        $this->module_key             = '8d2a2f65a77a8021da5d5ffccc9bbd2b';
        $this->ps_versions_compliancy = array('min' => '1.', 'max' => _PS_VERSION_);

        parent::__construct();

        $this->displayName = $this->l('Buckaroo Payments') . ' (v ' . $this->version . ')';
        $this->description = $this->l('Buckaroo Payment module. Compatible with PrestaShop version 1.6.x + 1.7.x');

        $this->confirmUninstall = $this->l('Are you sure you want to delete Buckaroo Payments module?');
        $this->tpl_folder       = 'buckaroo3';
        
        $response = ResponseFactory::getResponse();
        if($response){
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

    public function hookDisplayAdminOrderMainBottom($params)
    {
        $order         = new Order($params["id_order"]);
        $payments      = $order->getOrderPaymentCollection();
        $messages      = '';
        $messageStatus = 0;
        if (!empty($this->context->cookie->refundMessage)) {
            $messages              = $this->context->cookie->refundMessage;
            $messageStatus         = $this->context->cookie->refundStatus;
            $this->context->cookie->__set('refundMessage', null);
            $this->context->cookie->__set('refundStatus', null);
            $this->context->cookie->write();
        }
        $paymentInfo = array();
        $transactionIds = array();

        foreach ($payments as $payment) {
            $transactionIds[] = "'".$payment->transaction_id."'";
        }
        $transactionList = $this->getAllRefundedTransactions($transactionIds);

        $payments = iterator_to_array($payments);
        foreach ($payments as $payment) {
            $availableAmount = $payment->amount;

            if((float)$payment->amount > 0) {
                $availableAmount = (float)$payment->amount - $this->getRefundedAmountForTransaction(
                    $payment->transaction_id,
                    $payments,
                    $transactionList
                );
            }
            /* @var $payment OrderPaymentCore */
            $paymentInfo[$payment->id] = array(
                "available_amount" =>  number_format($availableAmount, 2)
            );
        }
        $buckarooFee = $this->getBuckarooFeeByCartId($order->id_cart);
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
    /**
     * Get all transaction_id grouped by original transaction
     *
     * @param array $transactionIds
     *
     * @return array
     */
    protected function getAllRefundedTransactions(array $transactionIds)
    {
        if(empty($transactionIds)){
            return 0;
        }

        $transactionIds = implode(",",$transactionIds);
        $transactions = Db::getInstance()->query(
            'SELECT `transaction_id`, `original_transaction`
            FROM `' . _DB_PREFIX_ . 'buckaroo_transactions`
            WHERE `original_transaction` IN (' . $transactionIds . ')'
        );

        $refunds = [];
        foreach ($transactions as $transaction) {
            if (!isset($refunds[$transaction['original_transaction']])) {
                $refunds[$transaction['original_transaction']] = array();
            }
            $refunds[$transaction['original_transaction']][] = $transaction['transaction_id'];
        }
        return $refunds;
    }
    /**
     * Get refund done for transaction
     *
     * @param string $transactionId
     * @param array $payments
     * @param array $transactionList
     *
     * @return float
     */
    public function getRefundedAmountForTransaction(
        string $transactionId,
        $payments,
        array $transactionList
    )
    {
        if (!isset($transactionList[$transactionId])) {
            return 0;
        }
        $refundsDone = [];

        foreach ($payments as $payment) {
            if (in_array($payment->transaction_id, $transactionList[$transactionId])) {
                $refundsDone[] = $payment;
            }
        }

        return (-1) * array_reduce(
            $refundsDone,
            function($carry, $payment) {
                /** @var OrderPaymentCore $payment */
                return $carry + (float)$payment->amount;
            },
            0
        );
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

    private function installTab()
    {
        $parent_tab = new Tab();
        $parent_tab->name[$this->context->language->id] = $this->l('Buckaroo Payments');
        $parent_tab->class_name = 'AdminBuckaroo';
        $parent_tab->id_parent = Tab::getIdFromClassName('IMPROVE');
        $parent_tab->module = 'buckaroo3';
        $parent_tab->icon = 'buckaroo';
        $parent_tab->add();

        //Config
        $tab = new Tab();
        $tab->name[$this->context->language->id] = $this->l('Configure');
        $tab->class_name = 'AdminBuckaroo';
        $tab->id_parent = $parent_tab->id;
        $tab->module = 'buckaroo3';
        $tab->add();

        //Logs
        $tab = new Tab();
        $tab->name[$this->context->language->id] = $this->l('Logs');
        $tab->class_name = 'AdminBuckaroolog';
        $tab->id_parent = $parent_tab->id;
        $tab->module = 'buckaroo3';
        $tab->add();
        return true;
    }

    private function uninstallTab()
    {
        $moduleTabs = Tab::getCollectionFromModule('buckaroo3');
        if (!empty($moduleTabs)) {
            foreach ($moduleTabs as $moduleTab) {
                $moduleTab->delete();
            }
        }
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook(
            'header'
        ) || !$this->registerHook('paymentReturn')
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('displayAdminOrderMainBottom')
            || !$this->registerHook('displayOrderConfirmation')
            || !$this->installTab()
            || !$this->createTransactionTable()
            || !$this->registerHook('actionEmailSendBefore')
            || !$this->registerHook('displayPDFInvoice')
            || !$this->registerHook('displayBackOfficeHeader')
    ) {
            return false;
        }
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
        Configuration::updateValue('BUCKAROO_TRANSACTION_LABEL', '');
        Configuration::updateValue('BUCKAROO_TRANSACTION_FEE', '');

        Configuration::updateValue('BUCKAROO_PAYPAL_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_PAYPAL_SELLER_PROTECTION_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_PAYPAL_TEST', '1');
        Configuration::updateValue('BUCKAROO_PAYPAL_LABEL', '');
        Configuration::updateValue('BUCKAROO_PAYPAL_FEE', '');
        Configuration::updateValue('BUCKAROO_PAYPAL_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_PAYPAL_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_IDEAL_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_IDEAL_TEST', '1');
        Configuration::updateValue('BUCKAROO_IDEAL_LABEL', '');
        Configuration::updateValue('BUCKAROO_IDEAL_FEE', '');
        Configuration::updateValue('BUCKAROO_IDEAL_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_IDEAL_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_GIROPAY_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_GIROPAY_TEST', '1');
        Configuration::updateValue('BUCKAROO_GIROPAY_LABEL', '');
        Configuration::updateValue('BUCKAROO_GIROPAY_FEE', '');
        Configuration::updateValue('BUCKAROO_GIROPAY_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_GIROPAY_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_KBC_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_KBC_TEST', '1');
        Configuration::updateValue('BUCKAROO_KBC_LABEL', '');
        Configuration::updateValue('BUCKAROO_KBC_FEE', '');
        Configuration::updateValue('BUCKAROO_KBC_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_KBC_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_EPS_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_EPS_TEST', '1');
        Configuration::updateValue('BUCKAROO_EPS_LABEL', '');
        Configuration::updateValue('BUCKAROO_EPS_FEE', '');
        Configuration::updateValue('BUCKAROO_EPS_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_EPS_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_PRZELEWY24_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_PRZELEWY24_TEST', '1');
        Configuration::updateValue('BUCKAROO_PRZELEWY24_LABEL', '');
        Configuration::updateValue('BUCKAROO_PRZELEWY24_FEE', '');
        Configuration::updateValue('BUCKAROO_PRZELEWY24_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_PRZELEWY24_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_TRUSTLY_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_TRUSTLY_TEST', '1');
        Configuration::updateValue('BUCKAROO_TRUSTLY_LABEL', '');
        Configuration::updateValue('BUCKAROO_TRUSTLY_FEE', '');
        Configuration::updateValue('BUCKAROO_TRUSTLY_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_TRUSTLY_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_TINKA_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_TINKA_TEST', '1');
        Configuration::updateValue('BUCKAROO_TINKA_LABEL', '');
        Configuration::updateValue('BUCKAROO_TINKA_FEE', '');
        Configuration::updateValue('BUCKAROO_TINKA_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_TINKA_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_PAYPEREMAIL_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_PAYPEREMAIL_TEST', '1');
        Configuration::updateValue('BUCKAROO_PAYPEREMAIL_LABEL', '');
        Configuration::updateValue('BUCKAROO_PAYPEREMAIL_FEE', '');
        Configuration::updateValue('BUCKAROO_PAYPEREMAIL_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_PAYPEREMAIL_MAX_VALUE', '');
        Configuration::updateValue('BUCKAROO_PAYPEREMAIL_SEND_EMAIL', '1');
        Configuration::updateValue('BUCKAROO_PAYPEREMAIL_EXPIRE_DAYS', '7');
        Configuration::updateValue('BUCKAROO_PAYPEREMAIL_ALLOWED_METHODS', 'ideal');

        Configuration::updateValue('BUCKAROO_PAYCONIQ_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_PAYCONIQ_TEST', '1');
        Configuration::updateValue('BUCKAROO_PAYCONIQ_LABEL', '');
        Configuration::updateValue('BUCKAROO_PAYCONIQ_FEE', '');
        Configuration::updateValue('BUCKAROO_PAYCONIQ_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_PAYCONIQ_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_MISTERCASH_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_MISTERCASH_TEST', '1');
        Configuration::updateValue('BUCKAROO_MISTERCASH_LABEL', '');
        Configuration::updateValue('BUCKAROO_MISTERCASH_FEE', '');
        Configuration::updateValue('BUCKAROO_MISTERCASH_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_MISTERCASH_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_GIFTCARD_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_GIFTCARD_TEST', '1');
        Configuration::updateValue('BUCKAROO_GIFTCARD_LABEL', '');
        Configuration::updateValue('BUCKAROO_GIFTCARD_FEE', '');
        Configuration::updateValue('BUCKAROO_GIFTCARD_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_GIFTCARD_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_CREDITCARD_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_CREDITCARD_TEST', '1');
        Configuration::updateValue('BUCKAROO_CREDITCARD_LABEL', '');
        Configuration::updateValue('BUCKAROO_CREDITCARD_FEE', '');
        Configuration::updateValue('BUCKAROO_CREDITCARD_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_CREDITCARD_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_SOFORTBANKING_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_SOFORTBANKING_TEST', '1');
        Configuration::updateValue('BUCKAROO_SOFORTBANKING_LABEL', '');
        Configuration::updateValue('BUCKAROO_SOFORTBANKING_FEE', '');
        Configuration::updateValue('BUCKAROO_SOFORTBANKING_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_SOFORTBANKING_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_BELFIUS_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_BELFIUS_TEST', '1');
        Configuration::updateValue('BUCKAROO_BELFIUS_LABEL', '');
        Configuration::updateValue('BUCKAROO_BELFIUS_FEE', '');
        Configuration::updateValue('BUCKAROO_BELFIUSMIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_BELFIUS_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_TRANSFER_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_TRANSFER_TEST', '1');
        Configuration::updateValue('BUCKAROO_TRANSFER_LABEL', '');
        Configuration::updateValue('BUCKAROO_TRANSFER_FEE', '');
        Configuration::updateValue('BUCKAROO_TRANSFER_DATEDUE', '14');
        Configuration::updateValue('BUCKAROO_TRANSFER_SENDMAIL', '0');
        Configuration::updateValue('BUCKAROO_TRANSFER_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_TRANSFER_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_AFTERPAY_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_AFTERPAY_TEST', '1');
        Configuration::updateValue('BUCKAROO_AFTERPAY_LABEL', '');
        Configuration::updateValue('BUCKAROO_AFTERPAY_FEE', '');
        Configuration::updateValue('BUCKAROO_AFTERPAY_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_AFTERPAY_MAX_VALUE', '');
        Configuration::updateValue('BUCKAROO_AFTERPAY_DEFAULT_VAT', '2');
        Configuration::updateValue('BUCKAROO_AFTERPAY_WRAPPING_VAT', '2');
        Configuration::updateValue('BUCKAROO_AFTERPAY_TAXRATE', serialize(array()));
        Configuration::updateValue('BUCKAROO_AFTERPAY_CUSTOMER_TYPE', 'both');

        Configuration::updateValue('BUCKAROO_KLARNA_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_KLARNA_TEST', '1');
        Configuration::updateValue('BUCKAROO_KLARNA_LABEL', '');
        Configuration::updateValue('BUCKAROO_KLARNA_FEE', '');
        Configuration::updateValue('BUCKAROO_KLARNA_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_KLARNA_MAX_VALUE', '');
        Configuration::updateValue('BUCKAROO_KLARNA_DEFAULT_VAT', '2');
        Configuration::updateValue('BUCKAROO_KLARNA_WRAPPING_VAT', '2');
        Configuration::updateValue('BUCKAROO_KLARNA_TAXRATE', serialize(array()));

        Configuration::updateValue('BUCKAROO_APPLEPAY_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_APPLEPAY_TEST', '1');
        Configuration::updateValue('BUCKAROO_APPLEPAY_LABEL', '');
        Configuration::updateValue('BUCKAROO_APPLEPAY_FEE', '');
        Configuration::updateValue('BUCKAROO_APPLEPAY_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_APPLEPAY_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_IN3_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_IN3_TEST', '1');
        Configuration::updateValue('BUCKAROO_IN3_LABEL', '');
        Configuration::updateValue('BUCKAROO_IN3_FEE', '');
        Configuration::updateValue('BUCKAROO_IN3_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_IN3_MAX_VALUE', '');

        Configuration::updateValue('BUCKAROO_BILLINK_ENABLED', '0');
        Configuration::updateValue('BUCKAROO_BILLINK_TEST', '1');
        Configuration::updateValue('BUCKAROO_BILLINK_LABEL', '');
        Configuration::updateValue('BUCKAROO_BILLINK_FEE', '');
        Configuration::updateValue('BUCKAROO_BILLINK_MIN_VALUE', '');
        Configuration::updateValue('BUCKAROO_BILLINK_MAX_VALUE', '');
        Configuration::updateValue('BUCKAROO_BILLINK_DEFAULT_VAT', '2');
        Configuration::updateValue('BUCKAROO_BILLINK_WRAPPING_VAT', '2');
        Configuration::updateValue('BUCKAROO_BILLINK_TAXRATE', serialize(array()));
        Configuration::updateValue('BUCKAROO_BILLINK_CUSTOMER_TYPE', 'both');

        Configuration::updateValue('BUCKAROO_GLOBAL_POSITION',0);
        Configuration::updateValue('BUCKAROO_IDIN_POSITION',1);
        Configuration::updateValue('BUCKAROO_PAYPAL_POSITION',2);
        Configuration::updateValue('BUCKAROO_SDD_POSITION',3);
        Configuration::updateValue('BUCKAROO_IDEAL_POSITION',4);
        Configuration::updateValue('BUCKAROO_GIROPAY_POSITION',5);
        Configuration::updateValue('BUCKAROO_KBC_POSITION',6);
        Configuration::updateValue('BUCKAROO_EPS_POSITION',7);
        Configuration::updateValue('BUCKAROO_PAYPEREMAIL_POSITION',8);
        Configuration::updateValue('BUCKAROO_PAYCONIQ_POSITION',9);
        Configuration::updateValue('BUCKAROO_PRZELEWY24_POSITION',10);
        Configuration::updateValue('BUCKAROO_TINKA_POSITION',11);
        Configuration::updateValue('BUCKAROO_TRUSTLY_POSITION',12);
        Configuration::updateValue('BUCKAROO_MISTERCASH_POSITION',13);
        Configuration::updateValue('BUCKAROO_GIFTCARD_POSITION',14);
        Configuration::updateValue('BUCKAROO_CREDITCARD_POSITION',15);
        Configuration::updateValue('BUCKAROO_SOFORTBANKING_POSITION',16);
        Configuration::updateValue('BUCKAROO_TRANSFER_POSITION',17);
        Configuration::updateValue('BUCKAROO_AFTERPAY_POSITION',18);
        Configuration::updateValue('BUCKAROO_APPLEPAY_POSITION',19);
        Configuration::updateValue('BUCKAROO_KLARNA_POSITION',20);
        Configuration::updateValue('BUCKAROO_BELFIUS_POSITION',21);
        Configuration::updateValue('BUCKAROO_IN3_POSITION',22);
        Configuration::updateValue('BUCKAROO_BILLINK_POSITION',23);

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

        //Cookie SameSite fix
        Configuration::updateValue('PS_COOKIE_SAMESITE', 'None');

        //override
        $this->overrideClasses();

        return true;
    }

    protected function overrideClasses()
    {
        $source = _PS_ROOT_DIR_ . "/modules/buckaroo3/classes/Mail.php";
        $destinationDir = _PS_ROOT_DIR_ . "/override/classes/";
        $destinationFile = $destinationDir . "Mail.php";

        // Check if destination directory exists, create it if necessary
        if (!is_dir($destinationDir)) {
            if (!mkdir($destinationDir, 0755, true)) {
                throw new Exception("Failed to create destination directory '{$destinationDir}'");
            }
        }

        // Attempt to copy the file
        if (!copy($source, $destinationFile)) {
            throw new Exception("Failed to copy file from '{$source}' to '{$destinationFile}'");
        }
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
        $this->uninstallTab();
        $this->unregisterHook('displayBackOfficeHeader');
        $this->unregisterHook('displayAdminOrderMainBottom');
        $this->unregisterHook('displayOrderConfirmation');
        $this->unregisterHook('actionEmailSendBefore');
        $this->unregisterHook('displayPDFInvoice');

        // Clean configuration table
        Configuration::deleteByName('BUCKAROO_TEST');
        Configuration::deleteByName('BUCKAROO_MERCHANT_KEY');
        Configuration::deleteByName('BUCKAROO_SECRET_KEY');
        Configuration::deleteByName('BUCKAROO_TRANSACTION_LABEL');
        Configuration::deleteByName('BUCKAROO_TRANSACTION_FEE');
        //paypal
        Configuration::deleteByName('BUCKAROO_PAYPAL_ENABLED');
        Configuration::deleteByName('BUCKAROO_PAYPAL_SELLER_PROTECTION_ENABLED');
        Configuration::deleteByName('BUCKAROO_PAYPAL_TEST');
        Configuration::deleteByName('BUCKAROO_PAYPAL_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_PAYPAL_MAX_VALUE');

        //sepadirectdebit
        Configuration::deleteByName('BUCKAROO_SDD_ENABLED');
        Configuration::deleteByName('BUCKAROO_SDD_TEST');
        Configuration::deleteByName('BUCKAROO_SDD_LABEL');
        Configuration::deleteByName('BUCKAROO_SDD_FEE');
        Configuration::deleteByName('BUCKAROO_SDD_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_SDD_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_IDEAL_ENABLED');
        Configuration::deleteByName('BUCKAROO_IDEAL_TEST');
        Configuration::deleteByName('BUCKAROO_IDEAL_LABEL');
        Configuration::deleteByName('BUCKAROO_IDEAL_FEE');
        Configuration::deleteByName('BUCKAROO_IDEAL_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_IDEAL_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_GIROPAY_ENABLED');
        Configuration::deleteByName('BUCKAROO_GIROPAY_TEST');
        Configuration::deleteByName('BUCKAROO_GIROPAY_LABEL');
        Configuration::deleteByName('BUCKAROO_GIROPAY_FEE');
        Configuration::deleteByName('BUCKAROO_GIROPAY_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_GIROPAY_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_KBC_ENABLED');
        Configuration::deleteByName('BUCKAROO_KBC_TEST');
        Configuration::deleteByName('BUCKAROO_KBC_LABEL');
        Configuration::deleteByName('BUCKAROO_KBC_FEE');
        Configuration::deleteByName('BUCKAROO_KBC_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_KBC_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_EPS_ENABLED');
        Configuration::deleteByName('BUCKAROO_EPS_TEST');
        Configuration::deleteByName('BUCKAROO_EPS_LABEL');
        Configuration::deleteByName('BUCKAROO_EPS_FEE');
        Configuration::deleteByName('BUCKAROO_EPS__MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_EPS__MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_PRZELEWY24_ENABLED');
        Configuration::deleteByName('BUCKAROO_PRZELEWY24_TEST');
        Configuration::deleteByName('BUCKAROO_PRZELEWY24_LABEL');
        Configuration::deleteByName('BUCKAROO_PRZELEWY24_FEE');
        Configuration::deleteByName('BUCKAROO_PRZELEWY24_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_PRZELEWY24_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_TRUSTLY_ENABLED');
        Configuration::deleteByName('BUCKAROO_TRUSTLY_TEST');
        Configuration::deleteByName('BUCKAROO_TRUSTLY_LABEL');
        Configuration::deleteByName('BUCKAROO_TRUSTLY_FEE');
        Configuration::deleteByName('BUCKAROO_TRUSTLY_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_TRUSTLY_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_TINKA_ENABLED');
        Configuration::deleteByName('BUCKAROO_TINKA_TEST');
        Configuration::deleteByName('BUCKAROO_TINKA_LABEL');
        Configuration::deleteByName('BUCKAROO_TINKA_FEE');
        Configuration::deleteByName('BUCKAROO_TINKA_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_TINKA_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_PAYPEREMAIL_ENABLED');
        Configuration::deleteByName('BUCKAROO_PAYPEREMAIL_TEST');
        Configuration::deleteByName('BUCKAROO_PAYPEREMAIL_LABEL');
        Configuration::deleteByName('BUCKAROO_PAYPEREMAIL_FEE');
        Configuration::deleteByName('BUCKAROO_PAYPEREMAIL_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_PAYPEREMAIL_MAX_VALUE');
        Configuration::deleteByName('BUCKAROO_PAYPEREMAIL_SEND_EMAIL');
        Configuration::deleteByName('BUCKAROO_PAYPEREMAIL_EXPIRE_DAYS');
        Configuration::deleteByName('BUCKAROO_PAYPEREMAIL_ALLOWED_METHODS');

        Configuration::deleteByName('BUCKAROO_PAYCONIQ_ENABLED');
        Configuration::deleteByName('BUCKAROO_PAYCONIQ_TEST');
        Configuration::deleteByName('BUCKAROO_PAYCONIQ_LABEL');
        Configuration::deleteByName('BUCKAROO_PAYCONIQ_FEE');
        Configuration::deleteByName('BUCKAROO_PAYCONIQ_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_PAYCONIQ_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_MISTERCASH_ENABLED');
        Configuration::deleteByName('BUCKAROO_MISTERCASH_TEST');
        Configuration::deleteByName('BUCKAROO_MISTERCASH_LABEL');
        Configuration::deleteByName('BUCKAROO_MISTERCASH_FEE');
        Configuration::deleteByName('BUCKAROO_MISTERCASH_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_MISTERCASH_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_GIFTCARD_ENABLED');
        Configuration::deleteByName('BUCKAROO_GIFTCARD_TEST');
        Configuration::deleteByName('BUCKAROO_GIFTCARD_LABEL');
        Configuration::deleteByName('BUCKAROO_GIFTCARD_FEE');
        Configuration::deleteByName('BUCKAROO_GIFTCARD_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_GIFTCARD_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_CREDITCARD_ENABLED');
        Configuration::deleteByName('BUCKAROO_CREDITCARD_TEST');
        Configuration::deleteByName('BUCKAROO_CREDITCARD_LABEL');
        Configuration::deleteByName('BUCKAROO_CREDITCARD_FEE');
        Configuration::deleteByName('BUCKAROO_CREDITCARD_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_CREDITCARD_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_SOFORTBANKING_ENABLED');
        Configuration::deleteByName('BUCKAROO_SOFORTBANKING_TEST');
        Configuration::deleteByName('BUCKAROO_SOFORTBANKING_LABEL');
        Configuration::deleteByName('BUCKAROO_SOFORTBANKING_FEE');
        Configuration::deleteByName('BUCKAROO_SOFORTBANKING_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_SOFORTBANKING_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_BELFIUS_ENABLED');
        Configuration::deleteByName('BUCKAROO_BELFIUS_TEST');
        Configuration::deleteByName('BUCKAROO_BELFIUS_LABEL');
        Configuration::deleteByName('BUCKAROO_BELFIUS_FEE');
        Configuration::deleteByName('BUCKAROO_BELFIUS_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_BELFIUS_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_AFTERPAY_ENABLED');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_TEST');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_LABEL');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_FEE');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_MAX_VALUE');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_DEFAULT_VAT');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_WRAPPING_VAT');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_TAXRATE');
        Configuration::deleteByName('BUCKAROO_AFTERPAY_CUSTOMER_TYPE');

        Configuration::deleteByName('BUCKAROO_KLARNA_ENABLED');
        Configuration::deleteByName('BUCKAROO_KLARNA_TEST');
        Configuration::deleteByName('BUCKAROO_KLARNA_LABEL');
        Configuration::deleteByName('BUCKAROO_KLARNA_FEE');
        Configuration::deleteByName('BUCKAROO_KLARNA_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_KLARNA_MAX_VALUE');
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
        Configuration::deleteByName('BUCKAROO_APPLEPAY_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_APPLEPAY_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_IN3_ENABLED');
        Configuration::deleteByName('BUCKAROO_IN3_TEST');
        Configuration::deleteByName('BUCKAROO_IN3_LABEL');
        Configuration::deleteByName('BUCKAROO_IN3_FEE');
        Configuration::deleteByName('BUCKAROO_IN3_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_IN3_MAX_VALUE');

        Configuration::deleteByName('BUCKAROO_BILLINK_ENABLED');
        Configuration::deleteByName('BUCKAROO_BILLINK_TEST');
        Configuration::deleteByName('BUCKAROO_BILLINK_LABEL');
        Configuration::deleteByName('BUCKAROO_BILLINK_FEE');
        Configuration::deleteByName('BUCKAROO_BILLINK_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_BILLINK_MAX_VALUE');
        Configuration::deleteByName('BUCKAROO_BILLINK_DEFAULT_VAT');
        Configuration::deleteByName('BUCKAROO_BILLINK_WRAPPING_VAT');
        Configuration::deleteByName('BUCKAROO_BILLINK_TAXRATE');
        Configuration::deleteByName('BUCKAROO_BILLINK_CUSTOMER_TYPE');

        Configuration::deleteByName('BUCKAROO_TRANSFER_ENABLED');
        Configuration::deleteByName('BUCKAROO_TRANSFER_TEST');
        Configuration::deleteByName('BUCKAROO_TRANSFER_LABEL');
        Configuration::deleteByName('BUCKAROO_TRANSFER_FEE');
        Configuration::deleteByName('BUCKAROO_TRANSFER_MIN_VALUE');
        Configuration::deleteByName('BUCKAROO_TRANSFER_MAX_VALUE');
        Configuration::deleteByName('BUCKAROO_TRANSFER_DATEDUE');
        Configuration::deleteByName('BUCKAROO_TRANSFER_SENDMAIL');

        return true;
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/buckaroo3.admin.css', 'all');
    }

    public function getContent()
    {
        $this->context->controller->addJS($this->_path . 'views/js/buckaroo.admin.js');
        $this->context->controller->addJS($this->_path . 'views/js/jquery-ui.min.js');
        include_once _PS_MODULE_DIR_ . '/' . $this->name . '/buckaroo3_admin.php';
        $buckaroo_admin = new Buckaroo3Admin($this);
        return $buckaroo_admin->postProcess() . $buckaroo_admin->displayForm();
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
                'afterpay_show_coc'       => $this->showAfterpayCoc($cart),
                'billink_show_coc'        => $this->showBillinkCoc($cart),
                'idealIssuers'           => (new IssuersIdeal())->get()
            )
        );

        $payment_options = [];
        libxml_use_internal_errors(true);
        if (Config::get('BUCKAROO_IDEAL_ENABLED') && $this->isPaymentMethodAvailable($cart,  'IDEAL')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('IDEAL', 'Pay by iDeal'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'ideal']))
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_ideal.tpl'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_ideal.png?v');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_PAYPAL_ENABLED') && $this->isPaymentMethodAvailable($cart,  'PAYPAL')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('PAYPAL', 'Pay by PayPal'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'paypal']))
                ->setInputs($this->getBuckarooFeeInputs('PAYPAL'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_paypal.png?v');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_SDD_ENABLED') && $this->isPaymentMethodAvailable($cart,  'SDD')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('SDD', 'Pay by SEPA Direct Debit'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'sepadirectdebit'])) //phpcs:ignore
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_sepadirectdebit.tpl')) //phpcs:ignore
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_sepa_dd.png?v'); //phpcs:ignore
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_GIROPAY_ENABLED') && $this->isPaymentMethodAvailable($cart,  'GIROPAY')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('GIROPAY', 'Pay by GiroPay'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'giropay']))
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_giropay.tpl'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_giropay.png?v');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_KBC_ENABLED') && $this->isPaymentMethodAvailable($cart,  'KBC')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('KBC', 'Pay by KBC'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'kbc']))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_kbc.png?v');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_MISTERCASH_ENABLED') && $this->isPaymentMethodAvailable($cart,  'MISTERCASH')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('MISTERCASH', 'Pay by  Bancontact / Mister Cash'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'bancontactmrcash'])) //phpcs:ignore
                ->setInputs($this->getBuckarooFeeInputs('MISTERCASH'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_mistercash.png?vv'); //phpcs:ignore
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_GIFTCARD_ENABLED') && $this->isPaymentMethodAvailable($cart,  'GIFTCARD')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('GIFTCARD', 'Pay by Giftcards'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'giftcard']))
                ->setInputs($this->getBuckarooFeeInputs('GIFTCARD'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_giftcards.png?v');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_CREDITCARD_ENABLED') && $this->isPaymentMethodAvailable($cart,  'CREDITCARD')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('CREDITCARD', 'Pay by Creditcards'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'creditcard']))
                ->setInputs($this->getBuckarooFeeInputs('CREDITCARD'))
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_creditcard.tpl')) //phpcs:ignore
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_cc.png?v');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_SOFORTBANKING_ENABLED') && $this->isPaymentMethodAvailable($cart,  'SOFORTBANKING')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('SOFORTBANKING', 'Pay by Sofortbanking'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'sofortueberweisung'])) //phpcs:ignore
                ->setInputs($this->getBuckarooFeeInputs('SOFORTBANKING'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_sofort.png?v'); //phpcs:ignore
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_BELFIUS_ENABLED') && $this->isPaymentMethodAvailable($cart,  'BELFIUS')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('BELFIUS', 'Pay by Belfius'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'belfius'])) //phpcs:ignore
                ->setInputs($this->getBuckarooFeeInputs('BELFIUS'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_belfius.png?v'); //phpcs:ignore
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_TRANSFER_ENABLED') && $this->isPaymentMethodAvailable($cart,  'TRANSFER')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('TRANSFER', 'Pay by Bank Transfer'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'transfer']))
                ->setInputs($this->getBuckarooFeeInputs('TRANSFER'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_transfer.png?vv1');
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_AFTERPAY_ENABLED') && $this->isPaymentMethodAvailable($cart,  'AFTERPAY') && $this->isAfterpayAvailable($cart)) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('AFTERPAY', 'Riverty | AfterPay'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'afterpay'])) //phpcs:ignore
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_afterpay.tpl')) //phpcs:ignore
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_afterpay.png?vv'); //phpcs:ignore
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_KLARNA_ENABLED') && $this->isPaymentMethodAvailable($cart,  'KLARNA')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('KLARNA', 'KlarnaKP'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'klarna'])) //phpcs:ignore
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_klarna.tpl'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_klarna.png?v'); //phpcs:ignore
            $payment_options[] = $newOption;
        }
        if (Config::get('BUCKAROO_APPLEPAY_ENABLED') && $this->isPaymentMethodAvailable($cart,  'APPLEPAY')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('APPLEPAY', 'Apple Pay'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'applepay'])) //phpcs:ignore
                ->setInputs($this->getBuckarooFeeInputs('APPLEPAY'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_applepay.png?v'); //phpcs:ignore
            $payment_options[] = $newOption;
        }

        if (Config::get('BUCKAROO_IN3_ENABLED') && $this->isPaymentMethodAvailable($cart,  'IN3') && $this->isIn3Available($cart)) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('IN3', 'In3'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'in3'])) //phpcs:ignore
                ->setInputs($this->getBuckarooFeeInputs('IN3'))
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_in3.tpl')) //phpcs:ignore
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_in3.png?v'); //phpcs:ignore
            $payment_options[] = $newOption;
        }

        if (Config::get('BUCKAROO_BILLINK_ENABLED') && $this->isPaymentMethodAvailable($cart,  'BILLINK')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('BILLINK', 'Billink'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'billink'])) //phpcs:ignore
                ->setInputs($this->getBuckarooFeeInputs('BILLINK'))
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_billink.tpl')) //phpcs:ignore
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_billink.png?v'); //phpcs:ignore
            $payment_options[] = $newOption;
        }

        if (Config::get('BUCKAROO_EPS_ENABLED') && $this->isPaymentMethodAvailable($cart,  'EPS')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('EPS', 'Pay by EPS'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'eps']))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_eps.png?v');
            $payment_options[] = $newOption;
        }

        if (Config::get('BUCKAROO_PRZELEWY24_ENABLED') && $this->isPaymentMethodAvailable($cart,  'PRZELEWY24')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('PRZELEWY24', 'Pay by Przelewy24'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'przelewy24']))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_przelewy24.png?v');
            $payment_options[] = $newOption;
        }

        if (Config::get('BUCKAROO_PAYPEREMAIL_ENABLED') && $this->isPaymentMethodAvailable($cart,  'PAYPEREMAIL')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('PAYPEREMAIL', 'Pay by PayPerEmail'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'payperemail']))
                ->setInputs($this->getBuckarooFeeInputs('PAYPEREMAIL'))
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_payperemail.tpl'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_payperemail.png?v');
            $payment_options[] = $newOption;
        }

        if (Config::get('BUCKAROO_PAYCONIQ_ENABLED') && $this->isPaymentMethodAvailable($cart,  'PAYCONIQ')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('PAYCONIQ', 'Pay by Payconiq'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'payconiq']))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_payconiq.png?v');
            $payment_options[] = $newOption;
        }

        if (Config::get('BUCKAROO_TINKA_ENABLED') && $this->isPaymentMethodAvailable($cart,  'TINKA')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('TINKA', 'Pay by Tinka'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'tinka']))
                ->setInputs($this->getBuckarooFeeInputs('TINKA'))
                ->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/payment_tinka.tpl'))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_tinka.png?v');
            $payment_options[] = $newOption;
        }

        if (Config::get('BUCKAROO_TRUSTLY_ENABLED') && $this->isPaymentMethodAvailable($cart,  'TRUSTLY')) {
            $newOption = new PaymentOption();
            $newOption->setCallToActionText($this->getBuckarooLabel('TRUSTLY', 'Pay by Trustly'))
                ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => 'trustly']))
                ->setLogo($this->_path . 'views/img/buckaroo_images/buckaroo_trustly.png?v');
            $payment_options[] = $newOption;
        }

        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        if (Tools::getValue("response_received")) {
            switch (Tools::getValue("response_received")) {
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

    public function hookDisplayHeader()
    {
        Media::addJsDef([
            'buckarooAjaxUrl' => $this->context->link->getModuleLink('buckaroo3', 'ajax'),
            'buckarooFees'    => $this->getBuckarooFees(),
            'buckarooMessages'=> [
                "validation" => [
                    "date"=>$this->l('Please enter correct birthdate date'),
                    "required"=>$this->l('Field is required'),
                    "agreement" => $this->l('Please accept licence agreements'),
                    "iban" => $this->l('A valid IBAN is required'),
                ]
            ]
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
            default:
                $payment_method_tr = $this->l($payment_method);
                break;
        }
        return $payment_method_tr;
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
            'PAYPAL',
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
            'APPLEPAY',
            'IN3',
            'BILLINK',
            'EPS',
            'PRZELEWY24',
            'TINKA',
            'TRUSTLY',
            'PAYPEREMAIL',
            'PAYCONIQ'
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
            $this->smarty->assign(array(
                'this_path' => _MODULE_DIR_ . $this->tpl_folder . '/',
            ));

            $content = $this->display(__FILE__, 'views/templates/hook/idin_box.tpl');
            $productExtraContent = new PrestaShop\PrestaShop\Core\Product\ProductExtraContent();
            $productExtraContent->setTitle($this->l('iDIN Info'));
            $productExtraContent->setContent($content);

            return array($productExtraContent);
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

    /**
     * Get address by id
     *
     * @param mixed $id
     *
     * @return Address|null
     */
    protected function getAddressById($id)
    {
        if (is_int($id)) {
            return;
        }
        return new Address($id);
    }
    /**
     * Check if company exists
     *
     * @param Address|null $address
     *
     * @return bool
     */
    protected function companyExists($address)
    {
        if ($address === null) {
            return false;
        }
        return strlen(trim($address->company)) !== 0;
    }
    public function showAfterpayCoc($cart)
    {
        $afterpay_customer_type = Config::get('BUCKAROO_AFTERPAY_CUSTOMER_TYPE');


        $idAddressInvoice = $cart->id_address_invoice !== 0 ? $cart->id_address_invoice : $cart->id_address_delivery;

        $billingAddress = $this->getAddressById($idAddressInvoice);
        $billingCountry = null;
        if($billingAddress !== null) {
            $billingCountry = Country::getIsoById($billingAddress->id_country);
        }

        $shippingAddress = $this->getAddressById($cart->id_address_delivery);
        $shippingCountry = null;
        if($shippingAddress !== null) {
            $shippingCountry = Country::getIsoById($shippingAddress->id_country);
        }

        return AfterPay::CUSTOMER_TYPE_B2B ===  $afterpay_customer_type ||
        (
            AfterPay::CUSTOMER_TYPE_B2C !==  $afterpay_customer_type &&
            (
                ($this->companyExists($shippingAddress) && $shippingCountry === 'NL') ||
                ($this->companyExists($billingAddress) && $billingCountry === 'NL')
            )
        );
    }

    public function showBillinkCoc($cart)
    {
        $billink_customer_type = Config::get('BUCKAROO_BILLINK_CUSTOMER_TYPE');

        $idAddressInvoice = $cart->id_address_invoice !== 0 ? $cart->id_address_invoice : $cart->id_address_delivery;

        $billingAddress = $this->getAddressById($idAddressInvoice);
        $billingCountry = null;
        if($billingAddress !== null) {
            $billingCountry = Country::getIsoById($billingAddress->id_country);
        }

        $shippingAddress = $this->getAddressById($cart->id_address_delivery);
        $shippingCountry = null;
        if($shippingAddress !== null) {
            $shippingCountry = Country::getIsoById($shippingAddress->id_country);
        }


        return Billink::CUSTOMER_TYPE_B2B ===  $billink_customer_type ||
        (
            Billink::CUSTOMER_TYPE_B2C !==  $billink_customer_type &&
            (
                ($this->companyExists($shippingAddress) && $shippingCountry === 'NL') ||
                ($this->companyExists($billingAddress) && $billingCountry === 'NL')
            )
        );
    }

    /**TODO
     * Check if payment method available
     *
     * @param Cart $cart
     * @return bool
     */
    protected function isPaymentMethodAvailable($cart, $paymentMethod)
    {
        //Check if payment method is available by amount
        return $this->isAvailableByAmount($cart->getOrderTotal(true, 3), $paymentMethod);
    }

    /**
     * Check if afterpay available
     *
     * @param Cart $cart
     * @return bool
     */
    protected function isAfterpayAvailable($cart)
    {
        $idAddressInvoice = $cart->id_address_invoice !== 0 ? $cart->id_address_invoice : $cart->id_address_delivery;
        $billingAddress = $this->getAddressById($idAddressInvoice);
        $billingCountry = null;
        if($billingAddress !== null) {
            $billingCountry = Country::getIsoById($billingAddress->id_country);
        }

        $shippingAddress = $this->getAddressById($cart->id_address_delivery);
        $shippingCountry = null;
        if($shippingAddress !== null) {
            $shippingCountry = Country::getIsoById($shippingAddress->id_country);
        }

        $customerType = Config::get('BUCKAROO_AFTERPAY_CUSTOMER_TYPE');
        if (AfterPay::CUSTOMER_TYPE_B2C !== $customerType) {
            $nlCompanyExists =
                ($this->companyExists($shippingAddress) && $shippingCountry === 'NL') ||
                ($this->companyExists($billingAddress) && $billingCountry === 'NL');
            if (AfterPay::CUSTOMER_TYPE_B2B === $customerType) {
                return $this->isAvailableByAmountB2B($cart->getOrderTotal(true, 3), 'AFTERPAY') && $nlCompanyExists;
            }

            // both customer types & a company is filled show if available b2b by amount
            if ($nlCompanyExists) {
                return $this->isAvailableByAmountB2B($cart->getOrderTotal(true, 3), 'AFTERPAY');
            }
        }

        return true;
    }

    /**
     * Check if payment is available by amount
     *
     * @param float $cartTotal
     * @param string $paymentMethod
     *
     * @return boolean
     */
    public function isAvailableByAmount(float $cartTotal, $paymentMethod)
    {
        $minAmount = (float)Config::get('BUCKAROO_'.$paymentMethod.'_MIN_VALUE');
        $maxAmount = (float)Config::get('BUCKAROO_'.$paymentMethod.'_MAX_VALUE');

        if ($minAmount == 0 && $maxAmount == 0) {
            return true;
        }

        return ($minAmount > 0 && $cartTotal > $minAmount) && ($maxAmount > 0 && $cartTotal < $maxAmount);
    }

    /**
     * Check if payment is available for b2b
     *
     * @param float $cartTotal
     *
     * @return boolean
     */
    public function isAvailableByAmountB2B(float $cartTotal, $paymentMethod)
    {
        $b2bMin = (float)Config::get('BUCKAROO_'.$paymentMethod.'_B2B_MIN_VALUE');
        $b2bMax = (float)Config::get('BUCKAROO_'.$paymentMethod.'_B2B_MAX_VALUE');

        if ($b2bMin == 0 && $b2bMax == 0) {
            return true;
        }

        return ($b2bMin > 0 && $cartTotal > $b2bMin) || ($b2bMax > 0 && $cartTotal < $b2bMax);
    }

    /**
     * Get billing country iso from cart
     *
     * @param Cart $cart
     *
     * @return string|null
     */
    protected function getBillingCountryIso($cart)
    {
        $idAddressInvoice = $cart->id_address_invoice !== 0 ? $cart->id_address_invoice : $cart->id_address_delivery;
        $billingAddress = $this->getAddressById($idAddressInvoice);

        if($billingAddress !== null) {
            return Country::getIsoById($billingAddress->id_country);
        }
    }

    /**
     * Is in3 available
     *
     * @param Cart $cart
     *
     * @return boolean
     */
    protected function isIn3Available($cart)
    {
        return $this->getBillingCountryIso($cart) === 'NL';
    }
}
