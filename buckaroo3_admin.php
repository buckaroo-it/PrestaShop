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

class Buckaroo3Admin
{
    private $error = '';

    public function __construct($module)
    {
        $this->module = $module;
    }

    //put your code here
    public function postProcess()
    {
        if (Tools::getValue('refresh_module')) {
            $this->module->createTransactionTable();
            $idTab = Tab::getIdFromClassName('AdminRefund');
            if ($idTab == 0) {
                $this->module->installModuleTab('AdminRefund', array(1 => 'Buckaroo Refunds'), -1);
            }
        } else {
            if (Tools::isSubmit('BUCKAROO_TEST')) {
                Configuration::updateValue('BUCKAROO_TEST', Tools::getValue('BUCKAROO_TEST'));
                Configuration::updateValue(
                    'BUCKAROO_ORDER_STATE_DEFAULT',
                    Tools::getValue('BUCKAROO_ORDER_STATE_DEFAULT')
                );
                Configuration::updateValue(
                    'BUCKAROO_ORDER_STATE_SUCCESS',
                    Tools::getValue('BUCKAROO_ORDER_STATE_SUCCESS')
                );
                Configuration::updateValue(
                    'BUCKAROO_ORDER_STATE_FAILED',
                    Tools::getValue('BUCKAROO_ORDER_STATE_FAILED')
                );
                Configuration::updateValue('BUCKAROO_MERCHANT_KEY', Tools::getValue('BUCKAROO_MERCHANT_KEY'));
                Configuration::updateValue('BUCKAROO_SECRET_KEY', Tools::getValue('BUCKAROO_SECRET_KEY'));
                Configuration::updateValue('BUCKAROO_TRANSACTION_LABEL', Tools::getValue('BUCKAROO_TRANSACTION_LABEL'));
                Configuration::updateValue('BUCKAROO_TRANSACTION_FEE', Tools::getValue('BUCKAROO_TRANSACTION_FEE'));

                Configuration::updateValue(
                    'BUCKAROO_PGST_PAYMENT',
                    serialize(Tools::getValue('BUCKAROO_PGST_PAYMENT'))
                );
                
                Configuration::updateValue(
                    'BUCKAROO_PGBY_PAYMENT',
                    serialize(Tools::getValue('BUCKAROO_PGBY_PAYMENT'))
                );

                Configuration::updateValue(
                    'BUCKAROO_IDIN_CATEGORY',
                    serialize(Tools::getValue('BUCKAROO_IDIN_CATEGORY'))
                );

                Configuration::updateValue('BUCKAROO_IDIN_ENABLED', Tools::getValue('BUCKAROO_IDIN_ENABLED'));
                Configuration::updateValue('BUCKAROO_IDIN_TEST', Tools::getValue('BUCKAROO_IDIN_TEST'));
                Configuration::updateValue('BUCKAROO_IDIN_MODE', Tools::getValue('BUCKAROO_IDIN_MODE'));
                Configuration::updateValue('BUCKAROO_PAYPAL_ENABLED', Tools::getValue('BUCKAROO_PAYPAL_ENABLED'));
                Configuration::updateValue('BUCKAROO_PAYPAL_SELLER_PROTECTION_ENABLED', Tools::getValue('BUCKAROO_PAYPAL_SELLER_PROTECTION_ENABLED'));
                Configuration::updateValue('BUCKAROO_PAYPAL_TEST', Tools::getValue('BUCKAROO_PAYPAL_TEST'));
                Configuration::updateValue('BUCKAROO_PAYPAL_LABEL', Tools::getValue('BUCKAROO_PAYPAL_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_BUCKAROOPAYPAL_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_BUCKAROOPAYPAL_FEE'))
                );

                Configuration::updateValue('BUCKAROO_SDD_ENABLED', Tools::getValue('BUCKAROO_SDD_ENABLED'));
                Configuration::updateValue('BUCKAROO_SDD_TEST', Tools::getValue('BUCKAROO_SDD_TEST'));
                Configuration::updateValue('BUCKAROO_SDD_LABEl', Tools::getValue('BUCKAROO_SDD_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_SDD_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_SDD_FEE'))
                );
                Configuration::updateValue('BUCKAROO_IDEAL_ENABLED', Tools::getValue('BUCKAROO_IDEAL_ENABLED'));
                Configuration::updateValue('BUCKAROO_IDEAL_TEST', Tools::getValue('BUCKAROO_IDEAL_TEST'));
                Configuration::updateValue('BUCKAROO_IDEAL_LABEL', Tools::getValue('BUCKAROO_IDEAL_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_IDEAL_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_IDEAL_FEE'))
                );
                Configuration::updateValue('BUCKAROO_GIROPAY_ENABLED', Tools::getValue('BUCKAROO_GIROPAY_ENABLED'));
                Configuration::updateValue('BUCKAROO_GIROPAY_TEST', Tools::getValue('BUCKAROO_GIROPAY_TEST'));
                Configuration::updateValue('BUCKAROO_GIROPAY_LABEL', Tools::getValue('BUCKAROO_GIROPAY_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_GIROPAY_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_GIROPAY_FEE'))
                );
                Configuration::updateValue('BUCKAROO_KBC_ENABLED', Tools::getValue('BUCKAROO_KBC_ENABLED'));
                Configuration::updateValue('BUCKAROO_KBC_TEST', Tools::getValue('BUCKAROO_KBC_TEST'));
                Configuration::updateValue('BUCKAROO_KBC_LABEL', Tools::getValue('BUCKAROO_KBC_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_KBC_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_KBC_FEE'))
                );
                Configuration::updateValue(
                    'BUCKAROO_MISTERCASH_ENABLED',
                    Tools::getValue('BUCKAROO_MISTERCASH_ENABLED')
                );
                Configuration::updateValue('BUCKAROO_MISTERCASH_TEST', Tools::getValue('BUCKAROO_MISTERCASH_TEST'));
                Configuration::updateValue('BUCKAROO_MISTERCASH_LABEL', Tools::getValue('BUCKAROO_MISTERCASH_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_MISTERCASH_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_MISTERCASH_FEE'))
                );
                Configuration::updateValue('BUCKAROO_GIFTCARD_ENABLED', Tools::getValue('BUCKAROO_GIFTCARD_ENABLED'));
                Configuration::updateValue('BUCKAROO_GIFTCARD_TEST', Tools::getValue('BUCKAROO_GIFTCARD_TEST'));
                Configuration::updateValue('BUCKAROO_GIFTCARD_LABEL', Tools::getValue('BUCKAROO_GIFTCARD_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_GIFTCARD_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_GIFTCARD_FEE'))
                );
                Configuration::updateValue(
                    'BUCKAROO_CREDITCARD_ENABLED',
                    Tools::getValue('BUCKAROO_CREDITCARD_ENABLED')
                );
                Configuration::updateValue('BUCKAROO_CREDITCARD_TEST', Tools::getValue('BUCKAROO_CREDITCARD_TEST'));
                Configuration::updateValue('BUCKAROO_CREDITCARD_LABEL', Tools::getValue('BUCKAROO_CREDITCARD_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_CREDITCARD_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_CREDITCARD_FEE'))
                );
                Configuration::updateValue(
                    'BUCKAROO_SOFORTBANKING_ENABLED',
                    Tools::getValue('BUCKAROO_SOFORTBANKING_ENABLED')
                );
                Configuration::updateValue(
                    'BUCKAROO_SOFORTBANKING_TEST',
                    Tools::getValue('BUCKAROO_SOFORTBANKING_TEST')
                );
                Configuration::updateValue(
                    'BUCKAROO_SOFORTBANKING_LABEL',
                    Tools::getValue('BUCKAROO_SOFORTBANKING_LABEL')
                );
                Configuration::updateValue(
                    'BUCKAROO_SOFORTBANKING_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_SOFORTBANKING_FEE'))
                );
                Configuration::updateValue(
                    'BUCKAROO_BELFIUS_ENABLED',
                    Tools::getValue('BUCKAROO_BELFIUS_ENABLED')
                );
                Configuration::updateValue(
                    'BUCKAROO_BELFIUS_TEST',
                    Tools::getValue('BUCKAROO_BELFIUS_TEST')
                );
                Configuration::updateValue(
                    'BUCKAROO_BELFIUS_LABEL',
                    Tools::getValue('BUCKAROO_BELFIUS_LABEL')
                );
                Configuration::updateValue(
                    'BUCKAROO_BELFIUS_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_BELFIUS_FEE'))
                );


                Configuration::updateValue(
                    'BUCKAROO_IN3_ENABLED',
                    Tools::getValue('BUCKAROO_IN3_ENABLED')
                );
                Configuration::updateValue(
                    'BUCKAROO_IN3_TEST',
                    Tools::getValue('BUCKAROO_IN3_TEST')
                );
                Configuration::updateValue(
                    'BUCKAROO_IN3_LABEL',
                    Tools::getValue('BUCKAROO_IN3_LABEL')
                );
                Configuration::updateValue(
                    'BUCKAROO_IN3_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_IN3_FEE'))
                );


                Configuration::updateValue('BUCKAROO_TRANSFER_ENABLED', Tools::getValue('BUCKAROO_TRANSFER_ENABLED'));
                Configuration::updateValue('BUCKAROO_TRANSFER_TEST', Tools::getValue('BUCKAROO_TRANSFER_TEST'));
                Configuration::updateValue('BUCKAROO_TRANSFER_LABEL', Tools::getValue('BUCKAROO_TRANSFER_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_TRANSFER_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_TRANSFER_FEE'))
                );
                Configuration::updateValue('BUCKAROO_TRANSFER_DATEDUE', Tools::getValue('BUCKAROO_TRANSFER_DATEDUE'));
                Configuration::updateValue('BUCKAROO_TRANSFER_SENDMAIL', Tools::getValue('BUCKAROO_TRANSFER_SENDMAIL'));

                Configuration::updateValue('BUCKAROO_AFTERPAY_ENABLED', Tools::getValue('BUCKAROO_AFTERPAY_ENABLED'));
                Configuration::updateValue('BUCKAROO_AFTERPAY_TEST', Tools::getValue('BUCKAROO_AFTERPAY_TEST'));
                Configuration::updateValue('BUCKAROO_AFTERPAY_LABEL', Tools::getValue('BUCKAROO_AFTERPAY_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_AFTERPAY_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_AFTERPAY_FEE'))
                );
                Configuration::updateValue(
                    'BUCKAROO_AFTERPAY_DEFAULT_VAT',
                    Tools::getValue('BUCKAROO_AFTERPAY_DEFAULT_VAT')
                );
                Configuration::updateValue(
                    'BUCKAROO_AFTERPAY_WRAPPING_VAT',
                    Tools::getValue('BUCKAROO_AFTERPAY_WRAPPING_VAT')
                );
                Configuration::updateValue(
                    'BUCKAROO_AFTERPAY_TAXRATE',
                    serialize(Tools::getValue('BUCKAROO_AFTERPAY_TAXRATE'))
                );

                Configuration::updateValue(
                    'BUCKAROO_AFTERPAY_CUSTOMER_TYPE',
                    Tools::getValue('BUCKAROO_AFTERPAY_CUSTOMER_TYPE')
                );

                Configuration::updateValue(
                    'BUCKAROO_AFTERPAY_B2B_MIN_VALUE',
                    Tools::getValue('BUCKAROO_AFTERPAY_B2B_MIN_VALUE')
                );

                Configuration::updateValue(
                    'BUCKAROO_AFTERPAY_B2B_MAX_VALUE',
                    Tools::getValue('BUCKAROO_AFTERPAY_B2B_MAX_VALUE')
                );

                Configuration::updateValue('BUCKAROO_KLARNA_ENABLED', Tools::getValue('BUCKAROO_KLARNA_ENABLED'));
                Configuration::updateValue('BUCKAROO_KLARNA_TEST', Tools::getValue('BUCKAROO_KLARNA_TEST'));
                Configuration::updateValue('BUCKAROO_KLARNA_LABEL', Tools::getValue('BUCKAROO_KLARNA_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_KLARNA_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_KLARNA_FEE'))
                );
                Configuration::updateValue(
                    'BUCKAROO_KLARNA_DEFAULT_VAT',
                    Tools::getValue('BUCKAROO_KLARNA_DEFAULT_VAT')
                );
                Configuration::updateValue(
                    'BUCKAROO_KLARNA_WRAPPING_VAT',
                    Tools::getValue('BUCKAROO_KLARNA_WRAPPING_VAT')
                );
                Configuration::updateValue(
                    'BUCKAROO_KLARNA_TAXRATE',
                    serialize(Tools::getValue('BUCKAROO_KLARNA_TAXRATE'))
                );
                Configuration::updateValue(
                    'BUCKAROO_KLARNA_BUSINESS',
                    serialize(Tools::getValue('BUCKAROO_KLARNA_BUSINESS'))
                );

                Configuration::updateValue('BUCKAROO_APPLEPAY_ENABLED', Tools::getValue('BUCKAROO_APPLEPAY_ENABLED'));
                Configuration::updateValue('BUCKAROO_APPLEPAY_TEST', Tools::getValue('BUCKAROO_APPLEPAY_TEST'));
                Configuration::updateValue('BUCKAROO_APPLEPAY_LABEL', Tools::getValue('BUCKAROO_APPLEPAY_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_APPLEPAY_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_APPLEPAY_FEE'))
                );
                
                Configuration::updateValue('BUCKAROO_BILLINK_ENABLED', Tools::getValue('BUCKAROO_BILLINK_ENABLED'));
                Configuration::updateValue('BUCKAROO_BILLINK_TEST', Tools::getValue('BUCKAROO_BILLINK_TEST'));
                Configuration::updateValue('BUCKAROO_BILLINK_LABEL', Tools::getValue('BUCKAROO_BILLINK_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_BILLINK_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_BILLINK_FEE'))
                );

                Configuration::updateValue(
                    'BUCKAROO_BILLINK_DEFAULT_VAT',
                    Tools::getValue('BUCKAROO_BILLINK_DEFAULT_VAT')
                );
                Configuration::updateValue(
                    'BUCKAROO_BILLINK_WRAPPING_VAT',
                    Tools::getValue('BUCKAROO_BILLINK_WRAPPING_VAT')
                );
                Configuration::updateValue(
                    'BUCKAROO_BILLINK_TAXRATE',
                    serialize(Tools::getValue('BUCKAROO_BILLINK_TAXRATE'))
                );

                Configuration::updateValue(
                    'BUCKAROO_BILLINK_CUSTOMER_TYPE',
                    Tools::getValue('BUCKAROO_BILLINK_CUSTOMER_TYPE')
                );

                Configuration::updateValue(
                    'BUCKAROO_BILLINK_B2B_MIN_VALUE',
                    Tools::getValue('BUCKAROO_BILLINK_B2B_MIN_VALUE')
                );

                Configuration::updateValue(
                    'BUCKAROO_BILLINK_B2B_MAX_VALUE',
                    Tools::getValue('BUCKAROO_BILLINK_B2B_MAX_VALUE')
                );

                Configuration::updateValue('BUCKAROO_EPS_ENABLED', Tools::getValue('BUCKAROO_EPS_ENABLED'));
                Configuration::updateValue('BUCKAROO_EPS_TEST', Tools::getValue('BUCKAROO_EPS_TEST'));
                Configuration::updateValue('BUCKAROO_EPS_LABEL', Tools::getValue('BUCKAROO_EPS_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_EPS_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_EPS_FEE'))
                );

                Configuration::updateValue('BUCKAROO_TINKA_ENABLED', Tools::getValue('BUCKAROO_TINKA_ENABLED'));
                Configuration::updateValue('BUCKAROO_TINKA_TEST', Tools::getValue('BUCKAROO_TINKA_TEST'));
                Configuration::updateValue('BUCKAROO_TINKA_LABEL', Tools::getValue('BUCKAROO_TINKA_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_TINKA_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_TINKA_FEE'))
                );

                Configuration::updateValue('BUCKAROO_TRUSTLY_ENABLED', Tools::getValue('BUCKAROO_TRUSTLY_ENABLED'));
                Configuration::updateValue('BUCKAROO_TRUSTLY_TEST', Tools::getValue('BUCKAROO_TRUSTLY_TEST'));
                Configuration::updateValue('BUCKAROO_TRUSTLY_LABEL', Tools::getValue('BUCKAROO_TRUSTLY_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_TRUSTLY_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_TRUSTLY_FEE'))
                );

                Configuration::updateValue('BUCKAROO_PRZELEWY24_ENABLED', Tools::getValue('BUCKAROO_PRZELEWY24_ENABLED'));
                Configuration::updateValue('BUCKAROO_PRZELEWY24_TEST', Tools::getValue('BUCKAROO_PRZELEWY24_TEST'));
                Configuration::updateValue('BUCKAROO_PRZELEWY24_LABEL', Tools::getValue('BUCKAROO_PRZELEWY24_LABEL'));
                Configuration::updateValue(
                    'BUCKAROO_PRZELEWY24_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_PRZELEWY24_FEE'))
                );

                Configuration::updateValue('BUCKAROO_PAYPEREMAIL_ENABLED', Tools::getValue('BUCKAROO_PAYPEREMAIL_ENABLED'));
                Configuration::updateValue('BUCKAROO_PAYPEREMAIL_TEST', Tools::getValue('BUCKAROO_PAYPEREMAIL_TEST'));
                Configuration::updateValue('BUCKAROO_PAYPEREMAIL_LABEL', Tools::getValue('BUCKAROO_PAYPEREMAIL_LABEL'));
                Configuration::updateValue('BUCKAROO_PAYPEREMAIL_SEND_EMAIL', Tools::getValue('BUCKAROO_PAYPEREMAIL_SEND_EMAIL'));
                Configuration::updateValue('BUCKAROO_PAYPEREMAIL_EXPIRE_DAYS', Tools::getValue('BUCKAROO_PAYPEREMAIL_EXPIRE_DAYS'));
                Configuration::updateValue('BUCKAROO_PAYPEREMAIL_ALLOWED_METHODS', Tools::getValue('BUCKAROO_PAYPEREMAIL_ALLOWED_METHODS'));

                Configuration::updateValue(
                    'BUCKAROO_PAYPEREMAIL_FEE',
                    $this->handlePaymentFee(Tools::getValue('BUCKAROO_PAYPEREMAIL_FEE'))
                );              

            }
        }
        return null;
    }

    public function displayForm()
    {
        // Get default Language
        $fields_value = array();
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $helper_head = new HelperForm();
        // Module, token and currentIndex
        $helper_head->module          = $this->module; //$helper_fields->module = $this->module;
        $helper_head->name_controller = $this->module->name; //$helper_fields->name_controller = $this->module->name;
        $helper_head->token           = Tools::getAdminTokenLite(
            'AdminModules'
        ); //$helper_fields->token = Tools::getAdminTokenLite('AdminModules');
        $helper_head->currentIndex =
            AdminController::$currentIndex . '&configure=' . $this->module->name;
        // Language
        $helper_head->default_form_language    = $default_lang; //$helper_fields->default_form_language = $default_lang;
        $helper_head->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper_head->title          = $this->module->displayName;
        $helper_head->show_toolbar   = true; // false -> remove toolbar
        $helper_head->toolbar_scroll = true; // yes - > Toolbar is always visible on the top of the screen.
        $helper_head->submit_action  = 'submit' . $this->module->name;
        $helper_head->toolbar_btn    = array(
            'save' => array(
                'desc' => $this->module->l('Save'),
                'js'   => "$('#buckaroo3settings_form').submit();",
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->module->l('Back to list'),
            ),
        );

        $fields_value['BUCKAROO_TEST']                   = Configuration::get('BUCKAROO_TEST');
        $fields_value['BUCKAROO_ORDER_STATE_DEFAULT']    = Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT') ?  
            Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT') : 1;
        $fields_value['BUCKAROO_ORDER_STATE_SUCCESS']    = Configuration::get('BUCKAROO_ORDER_STATE_SUCCESS') ?
            Configuration::get('BUCKAROO_ORDER_STATE_SUCCESS'):Configuration::get('PS_OS_PAYMENT');
        $fields_value['BUCKAROO_ORDER_STATE_FAILED']    = Configuration::get('BUCKAROO_ORDER_STATE_FAILED') ?
            Configuration::get('BUCKAROO_ORDER_STATE_FAILED') : Configuration::get('PS_OS_CANCELED');
        $fields_value['BUCKAROO_MERCHANT_KEY']           = Configuration::get('BUCKAROO_MERCHANT_KEY');
        $fields_value['BUCKAROO_SECRET_KEY']             = Configuration::get('BUCKAROO_SECRET_KEY');
        $fields_value['BUCKAROO_TRANSACTION_LABEL']      = Configuration::get('BUCKAROO_TRANSACTION_LABEL');
        $fields_value['BUCKAROO_TRANSACTION_FEE']      = Configuration::get('BUCKAROO_TRANSACTION_FEE');
        $fields_value['BUCKAROO_PGST_PAYMENT'] = array();
        $fields_value['BUCKAROO_PGBY_PAYMENT'] = array();
        $fields_value['BUCKAROO_IDIN_CATEGORY'] = array();

        $tmp_arr = Configuration::get('BUCKAROO_PGST_PAYMENT');
        if (!empty($tmp_arr)) {
            $c = unserialize($tmp_arr);
            if (is_array($c)) {
                $fields_value['BUCKAROO_PGST_PAYMENT'] = array_flip($c);
            }
        }

        $tmp_arr = Configuration::get('BUCKAROO_PGBY_PAYMENT');
        if (!empty($tmp_arr)) {
            $c = unserialize($tmp_arr);
            if (is_array($c)) {
                $fields_value['BUCKAROO_PGBY_PAYMENT'] = array_flip($c);
            }
        }

        $tmp_arr = Configuration::get('BUCKAROO_IDIN_CATEGORY');
        if (!empty($tmp_arr)) {
            $c = unserialize($tmp_arr);
            if (is_array($c)) {
                $fields_value['BUCKAROO_IDIN_CATEGORY'] = array_flip($c);
            }
        }

        $fields_value['BUCKAROO_IDIN_ENABLED']           = Configuration::get('BUCKAROO_IDIN_ENABLED');
        $fields_value['BUCKAROO_IDIN_TEST']              = Configuration::get('BUCKAROO_IDIN_TEST');
        $fields_value['BUCKAROO_IDIN_MODE']              = Configuration::get('BUCKAROO_IDIN_MODE');
        $fields_value['BUCKAROO_PAYPAL_ENABLED']           = Configuration::get('BUCKAROO_PAYPAL_ENABLED');
        $fields_value['BUCKAROO_PAYPAL_SELLER_PROTECTION_ENABLED'] = Configuration::get('BUCKAROO_PAYPAL_SELLER_PROTECTION_ENABLED');
        $fields_value['BUCKAROO_PAYPAL_TEST']              = Configuration::get('BUCKAROO_PAYPAL_TEST');
        $fields_value['BUCKAROO_PAYPAL_LABEL']              = Configuration::get('BUCKAROO_PAYPAL_LABEL');
        $fields_value['BUCKAROO_BUCKAROOPAYPAL_FEE']       = Configuration::get('BUCKAROO_BUCKAROOPAYPAL_FEE');
        $fields_value['BUCKAROO_SDD_ENABLED']              = Configuration::get('BUCKAROO_SDD_ENABLED');
        $fields_value['BUCKAROO_SDD_TEST']                 = Configuration::get('BUCKAROO_SDD_TEST');
        $fields_value['BUCKAROO_SDD_LABEL']                 = Configuration::get('BUCKAROO_SDD_LABEL');
        $fields_value['BUCKAROO_SDD_FEE']                 = Configuration::get('BUCKAROO_SDD_FEE');
        $fields_value['BUCKAROO_IDEAL_NOTIFICATIONDELAY']  = Configuration::get('BUCKAROO_IDEAL_NOTIFICATIONDELAY');
        $fields_value['BUCKAROO_IDEAL_ENABLED']            = Configuration::get('BUCKAROO_IDEAL_ENABLED');
        $fields_value['BUCKAROO_IDEAL_TEST']               = Configuration::get('BUCKAROO_IDEAL_TEST');
        $fields_value['BUCKAROO_IDEAL_LABEL']               = Configuration::get('BUCKAROO_IDEAL_LABEL');
        $fields_value['BUCKAROO_IDEAL_FEE']               = Configuration::get('BUCKAROO_IDEAL_FEE');
        $fields_value['BUCKAROO_GIROPAY_ENABLED']          = Configuration::get('BUCKAROO_GIROPAY_ENABLED');
        $fields_value['BUCKAROO_GIROPAY_TEST']             = Configuration::get('BUCKAROO_GIROPAY_TEST');
        $fields_value['BUCKAROO_GIROPAY_LABEL']             = Configuration::get('BUCKAROO_GIROPAY_LABEL');
        $fields_value['BUCKAROO_GIROPAY_FEE']             = Configuration::get('BUCKAROO_GIROPAY_FEE');

        $fields_value['BUCKAROO_KBC_ENABLED']          = Configuration::get('BUCKAROO_KBC_ENABLED');
        $fields_value['BUCKAROO_KBC_TEST']             = Configuration::get('BUCKAROO_KBC_TEST');
        $fields_value['BUCKAROO_KBC_LABEL']             = Configuration::get('BUCKAROO_KBC_LABEL');
        $fields_value['BUCKAROO_KBC_FEE']             = Configuration::get('BUCKAROO_KBC_FEE');

        $fields_value['BUCKAROO_EPS_ENABLED']          = Configuration::get('BUCKAROO_EPS_ENABLED');
        $fields_value['BUCKAROO_EPS_TEST']             = Configuration::get('BUCKAROO_EPS_TEST');
        $fields_value['BUCKAROO_EPS_LABEL']            = Configuration::get('BUCKAROO_EPS_LABEL');
        $fields_value['BUCKAROO_EPS_FEE']              = Configuration::get('BUCKAROO_EPS_FEE');

        $fields_value['BUCKAROO_PAYPEREMAIL_ENABLED']          = Configuration::get('BUCKAROO_PAYPEREMAIL_ENABLED');
        $fields_value['BUCKAROO_PAYPEREMAIL_TEST']             = Configuration::get('BUCKAROO_PAYPEREMAIL_TEST');
        $fields_value['BUCKAROO_PAYPEREMAIL_LABEL']            = Configuration::get('BUCKAROO_PAYPEREMAIL_LABEL');
        $fields_value['BUCKAROO_PAYPEREMAIL_FEE']              = Configuration::get('BUCKAROO_PAYPEREMAIL_FEE');
        $fields_value['BUCKAROO_PAYPEREMAIL_SEND_EMAIL']       = Configuration::get('BUCKAROO_PAYPEREMAIL_SEND_EMAIL');
        $fields_value['BUCKAROO_PAYPEREMAIL_EXPIRE_DAYS']      = Configuration::get('BUCKAROO_PAYPEREMAIL_EXPIRE_DAYS');
        $fields_value['BUCKAROO_PAYPEREMAIL_ALLOWED_METHODS']  = Configuration::get('BUCKAROO_PAYPEREMAIL_ALLOWED_METHODS');

        $fields_value['BUCKAROO_PRZELEWY24_ENABLED']          = Configuration::get('BUCKAROO_PRZELEWY24_ENABLED');
        $fields_value['BUCKAROO_PRZELEWY24_TEST']             = Configuration::get('BUCKAROO_PRZELEWY24_TEST');
        $fields_value['BUCKAROO_PRZELEWY24_LABEL']            = Configuration::get('BUCKAROO_PRZELEWY24_LABEL');
        $fields_value['BUCKAROO_PRZELEWY24_FEE']              = Configuration::get('BUCKAROO_PRZELEWY24_FEE');

        $fields_value['BUCKAROO_TINKA_ENABLED']          = Configuration::get('BUCKAROO_TINKA_ENABLED');
        $fields_value['BUCKAROO_TINKA_TEST']             = Configuration::get('BUCKAROO_TINKA_TEST');
        $fields_value['BUCKAROO_TINKA_LABEL']            = Configuration::get('BUCKAROO_TINKA_LABEL');
        $fields_value['BUCKAROO_TINKA_FEE']              = Configuration::get('BUCKAROO_TINKA_FEE');

        $fields_value['BUCKAROO_TRUSTLY_ENABLED']          = Configuration::get('BUCKAROO_TRUSTLY_ENABLED');
        $fields_value['BUCKAROO_TRUSTLY_TEST']             = Configuration::get('BUCKAROO_TRUSTLY_TEST');
        $fields_value['BUCKAROO_TRUSTLY_LABEL']            = Configuration::get('BUCKAROO_TRUSTLY_LABEL');
        $fields_value['BUCKAROO_TRUSTLY_FEE']              = Configuration::get('BUCKAROO_TRUSTLY_FEE');

        $fields_value['BUCKAROO_MISTERCASH_ENABLED']       = Configuration::get('BUCKAROO_MISTERCASH_ENABLED');
        $fields_value['BUCKAROO_MISTERCASH_TEST']          = Configuration::get('BUCKAROO_MISTERCASH_TEST');
        $fields_value['BUCKAROO_MISTERCASH_LABEL']          = Configuration::get('BUCKAROO_MISTERCASH_LABEL');
        $fields_value['BUCKAROO_MISTERCASH_FEE']          = Configuration::get('BUCKAROO_MISTERCASH_FEE');
        $fields_value['BUCKAROO_GIFTCARD_ENABLED']         = Configuration::get('BUCKAROO_GIFTCARD_ENABLED');
        $fields_value['BUCKAROO_GIFTCARD_TEST']            = Configuration::get('BUCKAROO_GIFTCARD_TEST');
        $fields_value['BUCKAROO_GIFTCARD_LABEL']            = Configuration::get('BUCKAROO_GIFTCARD_LABEL');
        $fields_value['BUCKAROO_GIFTCARD_FEE']            = Configuration::get('BUCKAROO_GIFTCARD_FEE');
        $fields_value['BUCKAROO_GIFTCARD_ALLOWED_CARDS']   = Configuration::get('BUCKAROO_GIFTCARD_ALLOWED_CARDS');
        $fields_value['BUCKAROO_CREDITCARD_ALLOWED_CARDS'] = Configuration::get('BUCKAROO_CREDITCARD_ALLOWED_CARDS');
        $fields_value['BUCKAROO_CREDITCARD_ENABLED']       = Configuration::get('BUCKAROO_CREDITCARD_ENABLED');
        $fields_value['BUCKAROO_CREDITCARD_TEST']          = Configuration::get('BUCKAROO_CREDITCARD_TEST');
        $fields_value['BUCKAROO_CREDITCARD_LABEL']          = Configuration::get('BUCKAROO_CREDITCARD_LABEL');
        $fields_value['BUCKAROO_CREDITCARD_FEE']          = Configuration::get('BUCKAROO_CREDITCARD_FEE');
        $fields_value['BUCKAROO_SOFORTBANKING_ENABLED']    = Configuration::get('BUCKAROO_SOFORTBANKING_ENABLED');
        $fields_value['BUCKAROO_SOFORTBANKING_TEST']       = Configuration::get('BUCKAROO_SOFORTBANKING_TEST');
        $fields_value['BUCKAROO_SOFORTBANKING_LABEL']       = Configuration::get('BUCKAROO_SOFORTBANKING_LABEL');
        $fields_value['BUCKAROO_SOFORTBANKING_FEE']       = Configuration::get('BUCKAROO_SOFORTBANKING_FEE');
        $fields_value['BUCKAROO_BELFIUS_ENABLED']    = Configuration::get('BUCKAROO_BELFIUS_ENABLED');
        $fields_value['BUCKAROO_BELFIUS_TEST']       = Configuration::get('BUCKAROO_BELFIUS_TEST');
        $fields_value['BUCKAROO_BELFIUS_LABEL']       = Configuration::get('BUCKAROO_BELFIUS_LABEL');
        $fields_value['BUCKAROO_BELFIUS_FEE']       = Configuration::get('BUCKAROO_BELFIUS_FEE');

        $fields_value['BUCKAROO_IN3_ENABLED']    = Configuration::get('BUCKAROO_IN3_ENABLED');
        $fields_value['BUCKAROO_IN3_TEST']       = Configuration::get('BUCKAROO_IN3_TEST');
        $fields_value['BUCKAROO_IN3_LABEL']       = Configuration::get('BUCKAROO_IN3_LABEL');
        $fields_value['BUCKAROO_IN3_FEE']       = Configuration::get('BUCKAROO_IN3_FEE');


        $fields_value['BUCKAROO_TRANSFER_ENABLED']         = Configuration::get('BUCKAROO_TRANSFER_ENABLED');
        $fields_value['BUCKAROO_TRANSFER_TEST']            = Configuration::get('BUCKAROO_TRANSFER_TEST');
        $fields_value['BUCKAROO_TRANSFER_LABEL']            = Configuration::get('BUCKAROO_TRANSFER_LABEL');
        $fields_value['BUCKAROO_TRANSFER_FEE']            = Configuration::get('BUCKAROO_TRANSFER_FEE');
        $fields_value['BUCKAROO_TRANSFER_DATEDUE']         = Configuration::get('BUCKAROO_TRANSFER_DATEDUE');
        $fields_value['BUCKAROO_TRANSFER_SENDMAIL']        = Configuration::get('BUCKAROO_TRANSFER_SENDMAIL');

        $fields_value['BUCKAROO_AFTERPAY_ENABLED']      = Configuration::get('BUCKAROO_AFTERPAY_ENABLED');
        $fields_value['BUCKAROO_AFTERPAY_TEST']         = Configuration::get('BUCKAROO_AFTERPAY_TEST');
        $fields_value['BUCKAROO_AFTERPAY_LABEL']         = Configuration::get('BUCKAROO_AFTERPAY_LABEL');
        $fields_value['BUCKAROO_AFTERPAY_FEE']         = Configuration::get('BUCKAROO_AFTERPAY_FEE');
        $fields_value['BUCKAROO_AFTERPAY_DEFAULT_VAT']  = Configuration::get('BUCKAROO_AFTERPAY_DEFAULT_VAT');
        $fields_value['BUCKAROO_AFTERPAY_WRAPPING_VAT'] = Configuration::get('BUCKAROO_AFTERPAY_WRAPPING_VAT');
        $fields_value['BUCKAROO_AFTERPAY_TAXRATE']      = unserialize(Configuration::get('BUCKAROO_AFTERPAY_TAXRATE'));
        $afterpayCustomerType = Configuration::get('BUCKAROO_AFTERPAY_CUSTOMER_TYPE');
        $fields_value['BUCKAROO_AFTERPAY_CUSTOMER_TYPE'] = strlen($afterpayCustomerType) === 0 ? AfterPay::CUSTOMER_TYPE_BOTH : $afterpayCustomerType;

        $fields_value['BUCKAROO_AFTERPAY_B2B_MIN_VALUE'] = (float)Configuration::get('BUCKAROO_AFTERPAY_B2B_MIN_VALUE');
        $fields_value['BUCKAROO_AFTERPAY_B2B_MAX_VALUE'] = (float)Configuration::get('BUCKAROO_AFTERPAY_B2B_MAX_VALUE');

        $fields_value['BUCKAROO_KLARNA_ENABLED']      = Configuration::get('BUCKAROO_KLARNA_ENABLED');
        $fields_value['BUCKAROO_KLARNA_TEST']         = Configuration::get('BUCKAROO_KLARNA_TEST');
        $fields_value['BUCKAROO_KLARNA_LABEL']         = Configuration::get('BUCKAROO_KLARNA_LABEL');
        $fields_value['BUCKAROO_KLARNA_FEE']         = Configuration::get('BUCKAROO_KLARNA_FEE');
        $fields_value['BUCKAROO_KLARNA_DEFAULT_VAT']  = Configuration::get('BUCKAROO_KLARNA_DEFAULT_VAT');
        $fields_value['BUCKAROO_KLARNA_WRAPPING_VAT'] = Configuration::get('BUCKAROO_KLARNA_WRAPPING_VAT');
        $fields_value['BUCKAROO_KLARNA_TAXRATE']      = unserialize(Configuration::get('BUCKAROO_KLARNA_TAXRATE'));
        $fields_value['BUCKAROO_KLARNA_BUSINESS']      = unserialize(Configuration::get('BUCKAROO_KLARNA_BUSINESS'));

        $fields_value['BUCKAROO_APPLEPAY_ENABLED']    = Configuration::get('BUCKAROO_APPLEPAY_ENABLED');
        $fields_value['BUCKAROO_APPLEPAY_TEST']       = Configuration::get('BUCKAROO_APPLEPAY_TEST');
        $fields_value['BUCKAROO_APPLEPAY_LABEL']       = Configuration::get('BUCKAROO_APPLEPAY_LABEL');
        $fields_value['BUCKAROO_APPLEPAY_FEE']       = Configuration::get('BUCKAROO_APPLEPAY_FEE');
        
        $fields_value['BUCKAROO_BILLINK_ENABLED']      = Configuration::get('BUCKAROO_BILLINK_ENABLED');
        $fields_value['BUCKAROO_BILLINK_TEST']         = Configuration::get('BUCKAROO_BILLINK_TEST');
        $fields_value['BUCKAROO_BILLINK_LABEL']         = Configuration::get('BUCKAROO_BILLINK_LABEL');
        $fields_value['BUCKAROO_BILLINK_FEE']         = Configuration::get('BUCKAROO_BILLINK_FEE');
        $fields_value['BUCKAROO_BILLINK_DEFAULT_VAT']  = Configuration::get('BUCKAROO_BILLINK_DEFAULT_VAT');
        $fields_value['BUCKAROO_BILLINK_WRAPPING_VAT'] = Configuration::get('BUCKAROO_BILLINK_WRAPPING_VAT');
        $fields_value['BUCKAROO_BILLINK_TAXRATE']      = unserialize(Configuration::get('BUCKAROO_BILLINK_TAXRATE'));
        $billinkCustomerType = Configuration::get('BUCKAROO_BILLINK_CUSTOMER_TYPE');
        $fields_value['BUCKAROO_BILLINK_CUSTOMER_TYPE'] = strlen($billinkCustomerType) === 0 ? Billink::CUSTOMER_TYPE_BOTH : $billinkCustomerType;

        $fields_value['BUCKAROO_BILLINK_B2B_MIN_VALUE'] = (float)Configuration::get('BUCKAROO_BILLINK_B2B_MIN_VALUE');
        $fields_value['BUCKAROO_BILLINK_B2B_MAX_VALUE'] = (float)Configuration::get('BUCKAROO_BILLINK_B2B_MAX_VALUE');

        //Global Settings
        $i              = 0;
        $orderStatesGet = OrderState::getOrderStates((int) (Configuration::get('PS_LANG_DEFAULT')));
        $orderStates = [];

        foreach ($orderStatesGet as $o) {
            $orderStates[] = array("text" => $o["name"], "value" => $o["id_order_state"]);;
        }

        $fields_form       = array();
        $fields_form[$i++] = array(
            'legend'  => $this->module->l('Global settings'),
            'name'    => 'GLOBAL',
            'enabled' => true,
            'test'    => Configuration::get('BUCKAROO_TEST'),
            'input'   => array(
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Merchant key'),
                    'name'     => 'BUCKAROO_MERCHANT_KEY',
                    'size'     => 25,
                    'required' => true,
                )
                ,
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Secret key'),
                    'name'     => 'BUCKAROO_SECRET_KEY',
                    'size'     => 80,
                    'required' => true,
                )
                ,
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Transaction label'),
                    'name'     => 'BUCKAROO_TRANSACTION_LABEL',
                    'size'     => 80,
                    'required' => true,
                )
                ,
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_ADVANCED_CONFIGURATION_ENABLED',
                    'label'    => $this->module->l('Advanced Configuration'),
                    'description' => $this->module->l('Advanced settings for the payment plugin'),
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type'    => 'select',
                    'name'    => 'BUCKAROO_ORDER_STATE_DEFAULT',
                    'label'   => $this->module->l('Pending payment status'),
                    'options' => $orderStates,
                    'description' => $this->module->l('This status will be given to orders pending payment.'),
                ),
                array(
                    'type'    => 'select',
                    'name'    => 'BUCKAROO_ORDER_STATE_SUCCESS',
                    'label'   => $this->module->l('Payment success status'),
                    'options' => $orderStates,
                    'description' => $this->module->l('This status will be given to orders paid.'),
                ),
                array(
                    'type'    => 'select',
                    'name'    => 'BUCKAROO_ORDER_STATE_FAILED',
                    'label'   => $this->module->l('Payment failed status'),
                    'options' => $orderStates,
                    'description' => $this->module->l('This status will be given to unsuccessful orders.'),
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'refresh_module',
                    'label'    => $this->module->l('Refresh module'),
                    'required' => true,
                ),
            ),
        );

        $cookie = new Cookie('ps');
        $cats = Category::getCategories((int)($cookie->id_lang), true, false);
        $categories = [];
        foreach ($cats as $value) {
            $categories[] = array(
                'text' => $value['name'],
                'value' => $value['id_category'],
            );
        }

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('iDIN verification Settings'),
            'name'    => 'PAYPAL',
            'test'    => Configuration::get('BUCKAROO_IDIN_TEST'),
            'enabled' => Configuration::get('BUCKAROO_IDIN_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_IDIN_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_IDIN_TEST',
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_IDIN_MODE',
                    'label'     => $this->module->l('iDIN verification mode'),
                    'description' => $this->module->l(
                        'iDIN verification mode'
                    ),
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('Global'),
                            'value' => '0',
                        ),
                        array(
                            'text'  => $this->module->l('Per product'),
                            'value' => '1',
                        ),
                        array(
                            'text'  => $this->module->l('Per category'),
                            'value' => '2',
                        ),
                    ),
                ),
                array(
                    'type'      => 'multiselect',
                    'name'      => 'BUCKAROO_IDIN_CATEGORY',
                    'label'     => $this->module->l('iDIN verification categories'),
                    'description' => $this->module->l(
                        'iDIN verification categories'
                    ),
                    'options'   => $categories
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('PayPal Settings'),
            'name'    => 'PAYPAL',
            'test'    => Configuration::get('BUCKAROO_PAYPAL_TEST'),
            'enabled' => Configuration::get('BUCKAROO_PAYPAL_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_PAYPAL_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_PAYPAL_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_PAYPAL_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_BUCKAROOPAYPAL_FEE',
                    'size'     => 80,
                ),
                array(
                    'label'    => $this->module->l('Paypal Seller Protection'),
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_PAYPAL_SELLER_PROTECTION_ENABLED',
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('SEPA Direct debit settings'),
            'name'    => 'SEPADD',
            'test'    => Configuration::get('BUCKAROO_SDD_TEST'),
            'enabled' => Configuration::get('BUCKAROO_SDD_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_SDD_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_SDD_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_SDD_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_SDD_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('IDeal settings'),
            'name'    => 'IDEAL',
            'test'    => Configuration::get('BUCKAROO_IDEAL_TEST'),
            'enabled' => Configuration::get('BUCKAROO_IDEAL_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_IDEAL_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_IDEAL_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_IDEAL_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_IDEAL_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            )
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('Giropay settings'),
            'name'    => 'GIROPAY',
            'test'    => Configuration::get('BUCKAROO_GIROPAY_TEST'),
            'enabled' => Configuration::get('BUCKAROO_GIROPAY_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_GIROPAY_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_GIROPAY_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_GIROPAY_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_GIROPAY_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('KBC settings'),
            'name'    => 'KBC',
            'test'    => Configuration::get('BUCKAROO_KBC_TEST'),
            'enabled' => Configuration::get('BUCKAROO_KBC_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_KBC_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_KBC_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_KBC_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_KBC_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('EPS settings'),
            'name'    => 'EPS',
            'test'    => Configuration::get('BUCKAROO_EPS_TEST'),
            'enabled' => Configuration::get('BUCKAROO_EPS_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_EPS_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_EPS_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_EPS_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_EPS_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('PayPerEmail settings'),
            'name'    => 'PAYPEREMAIL',
            'test'    => Configuration::get('BUCKAROO_PAYPEREMAIL_TEST'),
            'enabled' => Configuration::get('BUCKAROO_PAYPEREMAIL_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_PAYPEREMAIL_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_PAYPEREMAIL_TEST',
                ),
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_PAYPEREMAIL_SEND_EMAIL',
                    'label'    => $this->module->l('Send payment invite email'),
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Invite expire(days)'),
                    'name'     => 'BUCKAROO_PAYPEREMAIL_EXPIRE_DAYS',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Allowed methods separated by comma'),
                    'name'     => 'BUCKAROO_PAYPEREMAIL_ALLOWED_METHODS',
                    'size'     => 300,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_PAYPEREMAIL_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_PAYPEREMAIL_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('Przelewy24 settings'),
            'name'    => 'PRZELEWY24',
            'test'    => Configuration::get('BUCKAROO_PRZELEWY24_TEST'),
            'enabled' => Configuration::get('BUCKAROO_PRZELEWY24_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_PRZELEWY24_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_PRZELEWY24_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_PRZELEWY24_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_PRZELEWY24_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('Tinka settings'),
            'name'    => 'TINKA',
            'test'    => Configuration::get('BUCKAROO_TINKA_TEST'),
            'enabled' => Configuration::get('BUCKAROO_TINKA_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_TINKA_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_TINKA_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_TINKA_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_TINKA_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('Trustly settings'),
            'name'    => 'TRUSTLY',
            'test'    => Configuration::get('BUCKAROO_TRUSTLY_TEST'),
            'enabled' => Configuration::get('BUCKAROO_TRUSTLY_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_TRUSTLY_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_TRUSTLY_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_TRUSTLY_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_TRUSTLY_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('Bancontact / Mister Cash settings'),
            'name'    => 'MISTERCASH',
            'test'    => Configuration::get('BUCKAROO_MISTERCASH_TEST'),
            'enabled' => Configuration::get('BUCKAROO_MISTERCASH_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_MISTERCASH_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_MISTERCASH_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_MISTERCASH_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_MISTERCASH_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('GiftCard settings'),
            'name'    => 'GIFTCARD',
            'test'    => Configuration::get('BUCKAROO_GIFTCARD_TEST'),
            'enabled' => Configuration::get('BUCKAROO_GIFTCARD_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_GIFTCARD_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_GIFTCARD_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_GIFTCARD_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_GIFTCARD_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('CreditCard settings'),
            'name'    => 'CREDITCARD',
            'test'    => Configuration::get('BUCKAROO_CREDITCARD_TEST'),
            'enabled' => Configuration::get('BUCKAROO_CREDITCARD_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_CREDITCARD_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_CREDITCARD_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_CREDITCARD_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_CREDITCARD_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('Sofortbanking settings'),
            'name'    => 'SOFORTBANKING',
            'test'    => Configuration::get('BUCKAROO_SOFORTBANKING_TEST'),
            'enabled' => Configuration::get('BUCKAROO_SOFORTBANKING_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_SOFORTBANKING_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_SOFORTBANKING_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_SOFORTBANKING_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_SOFORTBANKING_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('Bank Transfer settings'),
            'name'    => 'TRANSFER',
            'test'    => (Configuration::get('BUCKAROO_TRANSFER_TEST') == '1' ? true : false),
            'enabled' => (Configuration::get('BUCKAROO_TRANSFER_ENABLED') == '1' ? true : false),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_TRANSFER_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_TRANSFER_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_TRANSFER_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_TRANSFER_FEE',
                    'size'     => 80,
                ),
                array(
                    'type'      => 'text',
                    'name'      => 'BUCKAROO_TRANSFER_DATEDUE',
                    'label'     => $this->module->l('Number of days to the date that the order should be payed.'),
                    'description' => $this->module->l(
                        'This is only for display purposes, to be able to use it in email templates.'
                    ),
                    'size'      => 4,
                    'required'  => true,
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_TRANSFER_SENDMAIL',
                    'label'     => $this->module->l('Send payment email'),
                    'description' => $this->module->l(
                        'Buckaroo sends an email to the customer with the payment procedures.'
                    ),
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('No'),
                            'value' => '0',
                        ),
                        array(
                            'text'  => $this->module->l('Yes'),
                            'value' => '1',
                        ),
                    ),
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );
        //TODO - Refactor taxes part
        $tx      = Tax::getTaxes(Configuration::get('PS_LANG_DEFAULT'));
        $taxes   = array();
        $taxes[] = "No tax";
        $defaultTaxvalues = [];
        $defaultTaxvalues[0] = 1;
        foreach ($tx as $t) {
            $taxes[$t["id_tax"]] = $t["name"];
            $defaultTaxvalues[$t["id_tax"]] = 1;
        }
        $taxvalues = $defaultTaxvalues;
        $savedtaxvalues = Configuration::get('BUCKAROO_AFTERPAY_TAXRATE');
        if (!empty($savedtaxvalues)) {
            $savedtaxvalues = unserialize($savedtaxvalues);
            if(count($savedtaxvalues)) {
                $taxvalues = $savedtaxvalues;
            }
        }
        $fields_form[$i++] = array(
            'legend'  => $this->module->l('Riverty | AfterPay Settings'),
            'name'    => 'AFTERPAY',
            'test'    => Configuration::get('BUCKAROO_AFTERPAY_TEST'),
            'enabled' => Configuration::get('BUCKAROO_AFTERPAY_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_AFTERPAY_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_AFTERPAY_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_AFTERPAY_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_AFTERPAY_FEE',
                    'size'     => 80,
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_AFTERPAY_CUSTOMER_TYPE',
                    'label'     => $this->module->l('Customer type'),
                    'description' => $this->module->l('This setting determines whether you accept AfterPay payments for B2C, B2B or both customer types. When B2B is selected, this method is only shown when a company name is entered in the checkout process.'),
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('Both'),
                            'value' => 'both',
                        ),
                        array(
                            'text'  => $this->module->l('B2B (Business-to-Business)'),
                            'value' => 'b2b',
                        ),
                        array(
                            'text'  => $this->module->l('B2C (Business-to-consumer)'),
                            'value' => 'b2c',
                        ),
                    )
                ),
                array(
                    'type'     => 'number',
                    'label'    => $this->module->l('Min order amount for B2B'),
                    'name'     => 'BUCKAROO_AFTERPAY_B2B_MIN_VALUE',
                    'description' => $this->module->l('The payment method shows only for orders with an order amount greater than the minimum amount.'),
                    'step'     => 0.01,
                    'min'      => 0
                ),
                array(
                    'type'     => 'number',
                    'label'    => $this->module->l('Max order amount for B2B'),
                    'name'     => 'BUCKAROO_AFTERPAY_B2B_MAX_VALUE',
                    'description' => $this->module->l('The payment method shows only for orders with an order amount smaller than the maximum amount.'),
                    'step'     => 0.01,
                    'min'      => 0
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_AFTERPAY_DEFAULT_VAT',
                    'label'     => $this->module->l('Default product Vat type'),
                    'description' => $this->module->l('Please select default vat type for your products'),
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('1 = High rate'),
                            'value' => '1',
                        ),
                        array(
                            'text'  => $this->module->l('2 = Low rate'),
                            'value' => '2',
                        ),
                        array(
                            'text'  => $this->module->l('3 = Zero rate'),
                            'value' => '3',
                        ),
                        array(
                            'text'  => $this->module->l('4 = Null rate'),
                            'value' => '4',
                        ),
                        array(
                            'text'  => $this->module->l('5 = Middle rate'),
                            'value' => '5',
                        ),
                    ),
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_AFTERPAY_WRAPPING_VAT',
                    'label'     => $this->module->l('Vat type for wrapping'),
                    'description' => $this->module->l('Please select  vat type for wrapping'),
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('1 = High rate'),
                            'value' => '1',
                        ),
                        array(
                            'text'  => $this->module->l('2 = Low rate'),
                            'value' => '2',
                        ),
                        array(
                            'text'  => $this->module->l('3 = Zero rate'),
                            'value' => '3',
                        ),
                        array(
                            'text'  => $this->module->l('4 = Null rate'),
                            'value' => '4',
                        ),
                        array(
                            'text'  => $this->module->l('5 = Middle rate'),
                            'value' => '5',
                        ),
                    ),
                ),
                array(
                    'type'       => 'taxrate',
                    'name'       => 'BUCKAROO_AFTERPAY_TAXRATE',
                    'label'      => $this->module->l('Select tax rates'),
                    'taxarray'   => $taxes,
                    'taxvalues'  => $taxvalues,
                    'taxoptions' => array(
                        array(
                            'text'  => $this->module->l('1 = High rate'),
                            'value' => '1',
                        ),
                        array(
                            'text'  => $this->module->l('2 = Low rate'),
                            'value' => '2',
                        ),
                        array(
                            'text'  => $this->module->l('3 = Zero rate'),
                            'value' => '3',
                        ),
                        array(
                            'text'  => $this->module->l('4 = Null rate'),
                            'value' => '4',
                        ),
                        array(
                            'text'  => $this->module->l('5 = Middle rate'),
                            'value' => '5',
                        ),
                    ),
                    'required'   => true,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('ApplePay settings'),
            'name'    => 'APPLEPAY',
            'test'    => Configuration::get('BUCKAROO_APPLEPAY_TEST'),
            'enabled' => Configuration::get('BUCKAROO_APPLEPAY_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_APPLEPAY_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_APPLEPAY_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_APPLEPAY_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_APPLEPAY_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $taxvalues = $defaultTaxvalues;
        $savedtaxvalues = Configuration::get('BUCKAROO_KLARNA_TAXRATE');
        if (!empty($savedtaxvalues)) {
            $savedtaxvalues = unserialize($savedtaxvalues);
            if(count($savedtaxvalues)) {
                $taxvalues = $savedtaxvalues;
            }
        }
        $fields_form[$i++] = array(
            'legend'  => $this->module->l('Klarna Pay later (pay) Settings'),
            'name'    => 'KLARNA',
            'test'    => Configuration::get('BUCKAROO_KLARNA_TEST'),
            'enabled' => Configuration::get('BUCKAROO_KLARNA_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_KLARNA_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_KLARNA_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_KLARNA_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_KLARNA_FEE',
                    'size'     => 80,
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_KLARNA_DEFAULT_VAT',
                    'label'     => $this->module->l('Default product Vat type'),
                    'description' => $this->module->l('Please select default vat type for your products'),
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('1 = High rate'),
                            'value' => '1',
                        ),
                        array(
                            'text'  => $this->module->l('2 = Low rate'),
                            'value' => '2',
                        ),
                        array(
                            'text'  => $this->module->l('3 = Zero rate'),
                            'value' => '3',
                        ),
                        array(
                            'text'  => $this->module->l('4 = Null rate'),
                            'value' => '4',
                        ),
                        array(
                            'text'  => $this->module->l('5 = Middle rate'),
                            'value' => '5',
                        ),
                    ),
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_KLARNA_WRAPPING_VAT',
                    'label'     => $this->module->l('Vat type for wrapping'),
                    'description' => $this->module->l('Please select  vat type for wrapping'),
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('1 = High rate'),
                            'value' => '1',
                        ),
                        array(
                            'text'  => $this->module->l('2 = Low rate'),
                            'value' => '2',
                        ),
                        array(
                            'text'  => $this->module->l('3 = Zero rate'),
                            'value' => '3',
                        ),
                        array(
                            'text'  => $this->module->l('4 = Null rate'),
                            'value' => '4',
                        ),
                        array(
                            'text'  => $this->module->l('5 = Middle rate'),
                            'value' => '5',
                        ),
                    ),
                ),
                array(
                    'type'       => 'taxrate',
                    'name'       => 'BUCKAROO_KLARNA_TAXRATE',
                    'label'      => $this->module->l('Select tax rates'),
                    'taxarray'   => $taxes,
                    'taxvalues'  => $taxvalues,
                    'taxoptions' => array(
                        array(
                            'text'  => $this->module->l('1 = High rate'),
                            'value' => '1',
                        ),
                        array(
                            'text'  => $this->module->l('2 = Low rate'),
                            'value' => '2',
                        ),
                        array(
                            'text'  => $this->module->l('3 = Zero rate'),
                            'value' => '3',
                        ),
                        array(
                            'text'  => $this->module->l('4 = Null rate'),
                            'value' => '4',
                        ),
                        array(
                            'text'  => $this->module->l('5 = Middle rate'),
                            'value' => '5',
                        ),
                    ),
                    'required'   => true,
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_KLARNA_BUSINESS',
                    'label'     => $this->module->l('Klarna payment method'),
                    'description' => $this->module->l('Select which paymethod must be used at Klarna.'),
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('B2C'),
                            'value' => 'B2C',
                        ),
                        array(
                            'text'  => $this->module->l('B2B'),
                            'value' => 'B2B',
                        ),
                    ),
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('Belfius settings'),
            'name'    => 'BELFIUS',
            'test'    => Configuration::get('BUCKAROO_BELFIUS_TEST'),
            'enabled' => Configuration::get('BUCKAROO_BELFIUS_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_BELFIUS_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_BELFIUS_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_BELFIUS_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_BELFIUS_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('In3 settings'),
            'name'    => 'IN3',
            'test'    => Configuration::get('BUCKAROO_IN3_TEST'),
            'enabled' => Configuration::get('BUCKAROO_IN3_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_IN3_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_IN3_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_IN3_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_IN3_FEE',
                    'size'     => 80,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );
        //Billink
        $tx      = Tax::getTaxes(Configuration::get('PS_LANG_DEFAULT'));
        $taxes   = array();
        $taxes[] = "No tax";
        $defaultTaxvalues = [];
        $defaultTaxvalues[0] = 1;
        foreach ($tx as $t) {
            $taxes[$t["id_tax"]] = $t["name"];
            $defaultTaxvalues[$t["id_tax"]] = 1;
        }
        $taxvalues = $defaultTaxvalues;
        $savedtaxvalues = Configuration::get('BUCKAROO_AFTERPAY_TAXRATE');
        if (!empty($savedtaxvalues)) {
            $savedtaxvalues = unserialize($savedtaxvalues);
            if(count($savedtaxvalues)) {
                $taxvalues = $savedtaxvalues;
            }
        }

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('Billink Settings'),
            'name'    => 'BILLINK',
            'test'    => Configuration::get('BUCKAROO_BILLINK_TEST'),
            'enabled' => Configuration::get('BUCKAROO_BILLINK_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_BILLINK_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_BILLINK_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_BILLINK_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_BILLINK_FEE',
                    'size'     => 80,
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_BILLINK_CUSTOMER_TYPE',
                    'label'     => $this->module->l('Customer type'),
                    'description' => $this->module->l('This setting determines whether you accept Billink payments for B2C, B2B or both customer types. When B2B is selected, this method is only shown when a company name is entered in the checkout process.'),
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('Both'),
                            'value' => 'both',
                        ),
                        array(
                            'text'  => $this->module->l('B2B (Business-to-Business)'),
                            'value' => 'b2b',
                        ),
                        array(
                            'text'  => $this->module->l('B2C (Business-to-consumer)'),
                            'value' => 'b2c',
                        ),
                    )
                ),
                array(
                    'type'     => 'number',
                    'label'    => $this->module->l('Min order amount for B2B'),
                    'name'     => 'BUCKAROO_BILLINK_B2B_MIN_VALUE',
                    'description' => $this->module->l('The payment method shows only for orders with an order amount greater than the minimum amount.'),
                    'step'     => 0.01,
                    'min'      => 0
                ),
                array(
                    'type'     => 'number',
                    'label'    => $this->module->l('Max order amount for B2B'),
                    'name'     => 'BUCKAROO_BILLINK_B2B_MAX_VALUE',
                    'description' => $this->module->l('The payment method shows only for orders with an order amount smaller than the maximum amount.'),
                    'step'     => 0.01,
                    'min'      => 0
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_BILLINK_DEFAULT_VAT',
                    'label'     => $this->module->l('Default product Vat type'),
                    'description' => $this->module->l('Please select default vat type for your products'),
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('1 = High rate'),
                            'value' => '1',
                        ),
                        array(
                            'text'  => $this->module->l('2 = Low rate'),
                            'value' => '2',
                        ),
                        array(
                            'text'  => $this->module->l('3 = Zero rate'),
                            'value' => '3',
                        ),
                        array(
                            'text'  => $this->module->l('4 = Null rate'),
                            'value' => '4',
                        ),
                        array(
                            'text'  => $this->module->l('5 = Middle rate'),
                            'value' => '5',
                        ),
                    ),
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_BILLINK_WRAPPING_VAT',
                    'label'     => $this->module->l('Vat type for wrapping'),
                    'description' => $this->module->l('Please select  vat type for wrapping'),
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('1 = High rate'),
                            'value' => '1',
                        ),
                        array(
                            'text'  => $this->module->l('2 = Low rate'),
                            'value' => '2',
                        ),
                        array(
                            'text'  => $this->module->l('3 = Zero rate'),
                            'value' => '3',
                        ),
                        array(
                            'text'  => $this->module->l('4 = Null rate'),
                            'value' => '4',
                        ),
                        array(
                            'text'  => $this->module->l('5 = Middle rate'),
                            'value' => '5',
                        ),
                    ),
                ),
                array(
                    'type'       => 'taxrate',
                    'name'       => 'BUCKAROO_BILLINK_TAXRATE',
                    'label'      => $this->module->l('Select tax rates'),
                    'taxarray'   => $taxes,
                    'taxvalues'  => $taxvalues,
                    'taxoptions' => array(
                        array(
                            'text'  => $this->module->l('1 = High rate'),
                            'value' => '1',
                        ),
                        array(
                            'text'  => $this->module->l('2 = Low rate'),
                            'value' => '2',
                        ),
                        array(
                            'text'  => $this->module->l('3 = Zero rate'),
                            'value' => '3',
                        ),
                        array(
                            'text'  => $this->module->l('4 = Null rate'),
                            'value' => '4',
                        ),
                        array(
                            'text'  => $this->module->l('5 = Middle rate'),
                            'value' => '5',
                        ),
                    ),
                    'required'   => true,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
            ),
        );

        $this->module->context->smarty->assign(
            array(
                'fields_form'  => $fields_form,
                'fields_value' => $fields_value,
                'form_action'  => Tools::safeOutput($_SERVER['REQUEST_URI']),
                'dir'          => dirname(__FILE__) . '/views/templates/admin',
                'top_error'    => $this->error,
            )
        );

        $tpl    = 'views/templates/admin/admin.tpl';
        $output = $this->module->display(dirname(__FILE__), $tpl);

        return $helper_head->generate() . $output;
    }

    private function handlePaymentFee($value)
    {
        return preg_replace('/[^0-9\.]/', '', $value);
    }
}
