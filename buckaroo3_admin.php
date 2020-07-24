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
                if (!empty(
                    $_FILES['BUCKAROO_CERTIFICATE']
                ) && !empty($_FILES['BUCKAROO_CERTIFICATE']['tmp_name'])
                    && !empty($_FILES['BUCKAROO_CERTIFICATE']['tmp_name'])
                ) {
                    if ($_FILES['BUCKAROO_CERTIFICATE']["error"] > 0) {
                        $this->error .= $this->module->l('Error uploading file');
                    } else {
                        if (stripos($_FILES['BUCKAROO_CERTIFICATE']["name"], '.pem', 1) === false) {
                            $error = 'Expected file extension: .pem<br />Get file type: ' . $_FILES['BUCKAROO_CERTIFICATE']["type"] . '<br />Get file name: ' . $_FILES['BUCKAROO_CERTIFICATE']["name"];//phpcs:ignore
                            $this->error .= $this->module->l('<b>Wrong file type!</b><br />' . $error);
                        } else {
                            $file_name = $_FILES['BUCKAROO_CERTIFICATE']['name'];
                            if (move_uploaded_file(
                                $_FILES['BUCKAROO_CERTIFICATE']['tmp_name'],
                                _PS_MODULE_DIR_ . $this->module->name . '/certificate/' . $file_name
                            )
                            ) {
                                Configuration::updateValue('BUCKAROO_CERTIFICATE_FILE', $file_name);
                                Configuration::updateValue(
                                    'BUCKAROO_CERTIFICATE',
                                    $file_name . ' (' . date('Y.m.d H:i') . ')'
                                );
                            } else {
                                $this->error .= $this->module->l('Error move uploaded file');
                            }
                        };
                    }
                }
                Configuration::updateValue('BUCKAROO_TEST', Tools::getValue('BUCKAROO_TEST'));
                Configuration::updateValue(
                    'BUCKAROO_ORDER_STATE_DEFAULT',
                    Tools::getValue('BUCKAROO_ORDER_STATE_DEFAULT')
                );
                Configuration::updateValue('BUCKAROO_MERCHANT_KEY', Tools::getValue('BUCKAROO_MERCHANT_KEY'));
                Configuration::updateValue('BUCKAROO_SECRET_KEY', Tools::getValue('BUCKAROO_SECRET_KEY'));
                Configuration::updateValue(
                    'BUCKAROO_CERTIFICATE_THUMBPRINT',
                    Tools::getValue('BUCKAROO_CERTIFICATE_THUMBPRINT')
                );
                Configuration::updateValue('BUCKAROO_TRANSACTION_LABEL', Tools::getValue('BUCKAROO_TRANSACTION_LABEL'));
                Configuration::updateValue('BUCKAROO_TRANSACTION_FEE', Tools::getValue('BUCKAROO_TRANSACTION_FEE'));
                Configuration::updateValue(
                    'BUCKAROO_TRANSACTION_RETURNURL',
                    Tools::getValue('BUCKAROO_TRANSACTION_RETURNURL')
                );
                Configuration::updateValue(
                    'BUCKAROO_TRANSACTION_CULTURE',
                    Tools::getValue('BUCKAROO_TRANSACTION_CULTURE')
                );

                Configuration::updateValue(
                    'BUCKAROO_PGST_PAYMENT',
                    serialize(Tools::getValue('BUCKAROO_PGST_PAYMENT'))
                );
                Configuration::updateValue(
                    'BUCKAROO_PGBY_PAYMENT',
                    serialize(Tools::getValue('BUCKAROO_PGBY_PAYMENT'))
                );

                Configuration::updateValue('BUCKAROO_PAYPAL_ENABLED', Tools::getValue('BUCKAROO_PAYPAL_ENABLED'));
                Configuration::updateValue('BUCKAROO_PAYPAL_TEST', Tools::getValue('BUCKAROO_PAYPAL_TEST'));
                Configuration::updateValue('BUCKAROO_PAYPAL_LABEL', Tools::getValue('BUCKAROO_PAYPAL_LABEL'));
                Configuration::updateValue('BUCKAROO_PAYPAL_FEE', Tools::getValue('BUCKAROO_PAYPAL_FEE'));
                Configuration::updateValue('BUCKAROO_EMPAYMENT_ENABLED', Tools::getValue('BUCKAROO_EMPAYMENT_ENABLED'));
                Configuration::updateValue('BUCKAROO_EMPAYMENT_TEST', Tools::getValue('BUCKAROO_EMPAYMENT_TEST'));
                Configuration::updateValue('BUCKAROO_EMPAYMENT_LABEL', Tools::getValue('BUCKAROO_EMPAYMENT_LABEL'));
                Configuration::updateValue('BUCKAROO_EMPAYMENT_FEE', Tools::getValue('BUCKAROO_EMPAYMENT_FEE'));
                Configuration::updateValue('BUCKAROO_DD_ENABLED', Tools::getValue('BUCKAROO_DD_ENABLED'));
                Configuration::updateValue('BUCKAROO_DD_TEST', Tools::getValue('BUCKAROO_DD_TEST'));
                Configuration::updateValue('BUCKAROO_DD_LABEL', Tools::getValue('BUCKAROO_DD_LABEL'));
                Configuration::updateValue('BUCKAROO_DD_FEE', Tools::getValue('BUCKAROO_DD_FEE'));
                Configuration::updateValue(
                    'BUCKAROO_DD_USECREDITMANAGMENT',
                    Tools::getValue('BUCKAROO_DD_USECREDITMANAGMENT')
                );
                Configuration::updateValue('BUCKAROO_DD_INVOICEDELAY', Tools::getValue('BUCKAROO_DD_INVOICEDELAY'));
                Configuration::updateValue('BUCKAROO_DD_DATEDUE', Tools::getValue('BUCKAROO_DD_DATEDUE'));
                Configuration::updateValue(
                    'BUCKAROO_DD_MAXREMINDERLEVEL',
                    Tools::getValue('BUCKAROO_DD_MAXREMINDERLEVEL')
                );
                Configuration::updateValue('BUCKAROO_SDD_ENABLED', Tools::getValue('BUCKAROO_SDD_ENABLED'));
                Configuration::updateValue('BUCKAROO_SDD_TEST', Tools::getValue('BUCKAROO_SDD_TEST'));
                Configuration::updateValue('BUCKAROO_SDD_LABEl', Tools::getValue('BUCKAROO_SDD_LABEL'));
                Configuration::updateValue('BUCKAROO_SDD_FEE', Tools::getValue('BUCKAROO_SDD_FEE'));
                Configuration::updateValue('BUCKAROO_IDEAL_ENABLED', Tools::getValue('BUCKAROO_IDEAL_ENABLED'));
                Configuration::updateValue('BUCKAROO_IDEAL_TEST', Tools::getValue('BUCKAROO_IDEAL_TEST'));
                Configuration::updateValue('BUCKAROO_IDEAL_LABEL', Tools::getValue('BUCKAROO_IDEAL_LABEL'));
                Configuration::updateValue('BUCKAROO_IDEAL_FEE', Tools::getValue('BUCKAROO_IDEAL_FEE'));
                Configuration::updateValue('BUCKAROO_GIROPAY_ENABLED', Tools::getValue('BUCKAROO_GIROPAY_ENABLED'));
                Configuration::updateValue('BUCKAROO_GIROPAY_TEST', Tools::getValue('BUCKAROO_GIROPAY_TEST'));
                Configuration::updateValue('BUCKAROO_GIROPAY_LABEL', Tools::getValue('BUCKAROO_GIROPAY_LABEL'));
                Configuration::updateValue('BUCKAROO_GIROPAY_FEE', Tools::getValue('BUCKAROO_GIROPAY_FEE'));
                Configuration::updateValue(
                    'BUCKAROO_PAYSAFECARD_ENABLED',
                    Tools::getValue('BUCKAROO_PAYSAFECARD_ENABLED')
                );
                Configuration::updateValue('BUCKAROO_PAYSAFECARD_TEST', Tools::getValue('BUCKAROO_PAYSAFECARD_TEST'));
                Configuration::updateValue('BUCKAROO_PAYSAFECARD_LABEL', Tools::getValue('BUCKAROO_PAYSAFECARD_LABEL'));
                Configuration::updateValue('BUCKAROO_PAYSAFECARD_FEE', Tools::getValue('BUCKAROO_PAYSAFECARD_FEE'));
                Configuration::updateValue(
                    'BUCKAROO_MISTERCASH_ENABLED',
                    Tools::getValue('BUCKAROO_MISTERCASH_ENABLED')
                );
                Configuration::updateValue('BUCKAROO_MISTERCASH_TEST', Tools::getValue('BUCKAROO_MISTERCASH_TEST'));
                Configuration::updateValue('BUCKAROO_MISTERCASH_LABEL', Tools::getValue('BUCKAROO_MISTERCASH_LABEL'));
                Configuration::updateValue('BUCKAROO_MISTERCASH_FEE', Tools::getValue('BUCKAROO_MISTERCASH_FEE'));
                Configuration::updateValue('BUCKAROO_GIFTCARD_ENABLED', Tools::getValue('BUCKAROO_GIFTCARD_ENABLED'));
                Configuration::updateValue('BUCKAROO_GIFTCARD_TEST', Tools::getValue('BUCKAROO_GIFTCARD_TEST'));
                Configuration::updateValue('BUCKAROO_GIFTCARD_LABEL', Tools::getValue('BUCKAROO_GIFTCARD_LABEL'));
                Configuration::updateValue('BUCKAROO_GIFTCARD_FEE', Tools::getValue('BUCKAROO_GIFTCARD_FEE'));
                Configuration::updateValue(
                    'BUCKAROO_CREDITCARD_ENABLED',
                    Tools::getValue('BUCKAROO_CREDITCARD_ENABLED')
                );
                Configuration::updateValue('BUCKAROO_CREDITCARD_TEST', Tools::getValue('BUCKAROO_CREDITCARD_TEST'));
                Configuration::updateValue('BUCKAROO_CREDITCARD_LABEL', Tools::getValue('BUCKAROO_CREDITCARD_LABEL'));
                Configuration::updateValue('BUCKAROO_CREDITCARD_FEEL', Tools::getValue('BUCKAROO_CREDITCARD_FEE'));
                Configuration::updateValue('BUCKAROO_EMAESTRO_ENABLED', Tools::getValue('BUCKAROO_EMAESTRO_ENABLED'));
                Configuration::updateValue('BUCKAROO_EMAESTRO_TEST', Tools::getValue('BUCKAROO_EMAESTRO_TEST'));
                Configuration::updateValue('BUCKAROO_EMAESTRO_LABEL', Tools::getValue('BUCKAROO_EMAESTRO_LABEL'));
                Configuration::updateValue('BUCKAROO_EMAESTRO_FEE', Tools::getValue('BUCKAROO_EMAESTRO_FEE'));
                Configuration::updateValue(
                    'BUCKAROO_SOFORTBANKING_ENABLED',
                    Tools::getValue('BUCKAROO_SOFORTBANKING_ENABLED')
                );
                Configuration::updateValue(
                    'BUCKAROO_SOFORTBANKING_TEST',
                    Tools::getValue('BUCKAROO_SOFORTBANKING_TEST')
                );
                Configuration::updateValue('BUCKAROO_SOFORTBANKING_LABEL',
                    Tools::getValue('BUCKAROO_SOFORTBANKING_LABEL')
                );
                Configuration::updateValue('BUCKAROO_SOFORTBANKING_FEE',
                    Tools::getValue('BUCKAROO_SOFORTBANKING_FEE')
                );
                Configuration::updateValue('BUCKAROO_TRANSFER_ENABLED', Tools::getValue('BUCKAROO_TRANSFER_ENABLED'));
                Configuration::updateValue('BUCKAROO_TRANSFER_TEST', Tools::getValue('BUCKAROO_TRANSFER_TEST'));
                Configuration::updateValue('BUCKAROO_TRANSFER_LABEL', Tools::getValue('BUCKAROO_TRANSFER_LABEL'));
                Configuration::updateValue('BUCKAROO_TRANSFER_FEE', Tools::getValue('BUCKAROO_TRANSFER_FEE'));
                Configuration::updateValue('BUCKAROO_TRANSFER_DATEDUE', Tools::getValue('BUCKAROO_TRANSFER_DATEDUE'));
                Configuration::updateValue('BUCKAROO_TRANSFER_SENDMAIL', Tools::getValue('BUCKAROO_TRANSFER_SENDMAIL'));

                Configuration::updateValue('BUCKAROO_AFTERPAY_ENABLED', Tools::getValue('BUCKAROO_AFTERPAY_ENABLED'));
                Configuration::updateValue('BUCKAROO_AFTERPAY_TEST', Tools::getValue('BUCKAROO_AFTERPAY_TEST'));
                Configuration::updateValue('BUCKAROO_AFTERPAY_LABEL', Tools::getValue('BUCKAROO_AFTERPAY_LABEL'));
                Configuration::updateValue('BUCKAROO_AFTERPAY_FEE', Tools::getValue('BUCKAROO_AFTERPAY_FEE'));
                Configuration::updateValue(
                    'BUCKAROO_AFTERPAY_SERVISS_NAME',
                    Tools::getValue('BUCKAROO_AFTERPAY_SERVISS_NAME')
                );
                Configuration::updateValue('BUCKAROO_AFTERPAY_BTB', Tools::getValue('BUCKAROO_AFTERPAY_BTB'));
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
        $helper_head->currentIndex = AdminController::$currentIndex . '&configure=' . $this->module->name; //$helper_fields->currentIndex = AdminController::$currentIndex.'&configure='.$this->module->name;
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
        $fields_value['BUCKAROO_ORDER_STATE_DEFAULT']    = Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');
        $fields_value['BUCKAROO_MERCHANT_KEY']           = Configuration::get('BUCKAROO_MERCHANT_KEY');
        $fields_value['BUCKAROO_SECRET_KEY']             = Configuration::get('BUCKAROO_SECRET_KEY');
        $fields_value['BUCKAROO_CERTIFICATE_THUMBPRINT'] = Configuration::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $fields_value['BUCKAROO_CERTIFICATE']            = Configuration::get('BUCKAROO_CERTIFICATE');
        $fields_value['BUCKAROO_TRANSACTION_LABEL']      = Configuration::get('BUCKAROO_TRANSACTION_LABEL');
        $fields_value['BUCKAROO_TRANSACTION_FEE']      = Configuration::get('BUCKAROO_TRANSACTION_FEE');
        $fields_value['BUCKAROO_TRANSACTION_RETURNURL']  = Configuration::get('BUCKAROO_TRANSACTION_RETURNURL');
        if (empty($fields_value['BUCKAROO_TRANSACTION_RETURNURL'])) {
            $fields_value['BUCKAROO_TRANSACTION_RETURNURL'] = 'http' . ((!empty(
                $_SERVER["HTTPS"]
            ) && $_SERVER["HTTPS"] == "on") ? 's' : '') . '://' . $_SERVER["SERVER_NAME"] . __PS_BASE_URI__ . 'index.php?fc=module&module=buckaroo3&controller=return';//phpcs:ignore
        }
        $fields_value['BUCKAROO_TRANSACTION_CULTURE'] = Configuration::get('BUCKAROO_TRANSACTION_CULTURE');

        $fields_value['BUCKAROO_PGST_PAYMENT'] = array();
        $fields_value['BUCKAROO_PGBY_PAYMENT'] = array();

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

        $fields_value['BUCKAROO_PAYPAL_ENABLED']           = Configuration::get('BUCKAROO_PAYPAL_ENABLED');
        $fields_value['BUCKAROO_PAYPAL_TEST']              = Configuration::get('BUCKAROO_PAYPAL_TEST');
        $fields_value['BUCKAROO_PAYPAL_LABEL']              = Configuration::get('BUCKAROO_PAYPAL_LABEL');
        $fields_value['BUCKAROO_PAYPAL_FEE']              = Configuration::get('BUCKAROO_PAYPAL_FEE');
        $fields_value['BUCKAROO_EMPAYMENT_ENABLED']        = Configuration::get('BUCKAROO_EMPAYMENT_ENABLED');
        $fields_value['BUCKAROO_EMPAYMENT_TEST']           = Configuration::get('BUCKAROO_EMPAYMENT_TEST');
        $fields_value['BUCKAROO_EMPAYMENT_LABEL']           = Configuration::get('BUCKAROO_EMPAYMENT_LABEL');
        $fields_value['BUCKAROO_EMPAYMENT_FEE']           = Configuration::get('BUCKAROO_EMPAYMENT_FEE');
        $fields_value['BUCKAROO_DD_ENABLED']               = Configuration::get('BUCKAROO_DD_ENABLED');
        $fields_value['BUCKAROO_DD_TEST']                  = Configuration::get('BUCKAROO_DD_TEST');
        $fields_value['BUCKAROO_DD_LABEL']                 = Configuration::get('BUCKAROO_DD_LABEL');
        $fields_value['BUCKAROO_DD_FEE']                 = Configuration::get('BUCKAROO_DD_FEE');
        $fields_value['BUCKAROO_DD_USECREDITMANAGMENT']    = Configuration::get('BUCKAROO_DD_USECREDITMANAGMENT');
        $fields_value['BUCKAROO_DD_INVOICEDELAY']          = Configuration::get('BUCKAROO_DD_INVOICEDELAY');
        $fields_value['BUCKAROO_DD_DATEDUE']               = Configuration::get('BUCKAROO_DD_DATEDUE');
        $fields_value['BUCKAROO_DD_MAXREMINDERLEVEL']      = Configuration::get('BUCKAROO_DD_MAXREMINDERLEVEL');
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
        $fields_value['BUCKAROO_PAYSAFECARD_ENABLED']      = Configuration::get('BUCKAROO_PAYSAFECARD_ENABLED');
        $fields_value['BUCKAROO_PAYSAFECARD_TEST']         = Configuration::get('BUCKAROO_PAYSAFECARD_TEST');
        $fields_value['BUCKAROO_PAYSAFECARD_LABEL']         = Configuration::get('BUCKAROO_PAYSAFECARD_LABEL');
        $fields_value['BUCKAROO_PAYSAFECARD_FEE']         = Configuration::get('BUCKAROO_PAYSAFECARD_FEE');
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
        $fields_value['BUCKAROO_EMAESTRO_ENABLED']         = Configuration::get('BUCKAROO_EMAESTRO_ENABLED');
        $fields_value['BUCKAROO_EMAESTRO_TEST']            = Configuration::get('BUCKAROO_EMAESTRO_TEST');
        $fields_value['BUCKAROO_EMAESTRO_LABEL']            = Configuration::get('BUCKAROO_EMAESTRO_LABEL');
        $fields_value['BUCKAROO_EMAESTRO_FEE']            = Configuration::get('BUCKAROO_EMAESTRO_FEE');
        $fields_value['BUCKAROO_SOFORTBANKING_ENABLED']    = Configuration::get('BUCKAROO_SOFORTBANKING_ENABLED');
        $fields_value['BUCKAROO_SOFORTBANKING_TEST']       = Configuration::get('BUCKAROO_SOFORTBANKING_TEST');
        $fields_value['BUCKAROO_SOFORTBANKING_LABEL']       = Configuration::get('BUCKAROO_SOFORTBANKING_LABEL');
        $fields_value['BUCKAROO_SOFORTBANKING_FEE']       = Configuration::get('BUCKAROO_SOFORTBANKING_FEE');
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
        $fields_value['BUCKAROO_AFTERPAY_SERVISS_NAME'] = Configuration::get('BUCKAROO_AFTERPAY_SERVISS_NAME');
        $fields_value['BUCKAROO_AFTERPAY_BTB']          = Configuration::get('BUCKAROO_AFTERPAY_BTB');
        $fields_value['BUCKAROO_AFTERPAY_DEFAULT_VAT']  = Configuration::get('BUCKAROO_AFTERPAY_DEFAULT_VAT');
        $fields_value['BUCKAROO_AFTERPAY_WRAPPING_VAT'] = Configuration::get('BUCKAROO_AFTERPAY_WRAPPING_VAT');
        $fields_value['BUCKAROO_AFTERPAY_TAXRATE']      = unserialize(Configuration::get('BUCKAROO_AFTERPAY_TAXRATE'));

        //Global Settings
        $i              = 0;
        $orderStatesGet = OrderState::getOrderStates((int) (Configuration::get('PS_LANG_DEFAULT')));
        $orderStates    = array();
        foreach ($orderStatesGet as $o) {
            $orderStates[] = array("text" => $o["name"], "value" => $o["id_order_state"]);
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
                    'type'  => 'certificate',
                    'label' => $this->module->l('Certificate'),
                    'name'  => 'BUCKAROO_CERTIFICATE',
                )
                ,
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Certificate thumbprint'),
                    'name'     => 'BUCKAROO_CERTIFICATE_THUMBPRINT',
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
                    'type'     => 'text',
                    'label'    => $this->module->l('Return url'),
                    'name'     => 'BUCKAROO_TRANSACTION_RETURNURL',
                    'size'     => 100,
                    'required' => true,
                )
                ,
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_TRANSACTION_CULTURE',
                    'label'     => $this->module->l('Language'),
                    'smalltext' => 'Payment engine language. Can be used only English, Dutch, French and German languege.',//phpcs:ignore
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('Use webshop culture'),
                            'value' => 'A',
                        ),
                        array(
                            'text'  => $this->module->l('English'),
                            'value' => 'en',
                        ),
                        array(
                            'text'  => $this->module->l('Dutch'),
                            'value' => 'nl',
                        ),
                        array(
                            'text'  => $this->module->l('French'),
                            'value' => 'fr',
                        ),
                        array(
                            'text'  => $this->module->l('German'),
                            'value' => 'de',
                        ),
                    ),
                ),
                array(
                    'type'    => 'select',
                    'name'    => 'BUCKAROO_ORDER_STATE_DEFAULT',
                    'label'   => $this->module->l('Default order status after order is created'),
                    'options' => $orderStates,
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
                    'name'     => 'BUCKAROO_PAYPAL_FEE',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
                array(
                    'type' => 'hidearea_end',
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
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
                array(
                    'type' => 'hidearea_end',
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
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
                array(
                    'type' => 'hidearea_end',
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
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('PaySafeCard settings'),
            'name'    => 'PAYSAFECARD',
            'test'    => Configuration::get('BUCKAROO_PAYSAFECARD_TEST'),
            'enabled' => Configuration::get('BUCKAROO_PAYSAFECARD_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_PAYSAFECARD_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_PAYSAFECARD_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_PAYSAFECARD_LABEL',
                    'size'     => 80,
                ),
                array(
                'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_PAYSAFECARD_FEE',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
                array(
                    'type' => 'hidearea_end',
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
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
                array(
                    'type' => 'hidearea_end',
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
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
                array(
                    'type' => 'hidearea_end',
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
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
            ),
        );

        $fields_form[$i++] = array(
            'legend'  => $this->module->l('EMaestro settings'),
            'name'    => 'EMAESTRO',
            'test'    => Configuration::get('BUCKAROO_EMAESTRO_TEST'),
            'enabled' => Configuration::get('BUCKAROO_EMAESTRO_ENABLED'),
            'input'   => array(
                array(
                    'type' => 'enabled',
                    'name' => 'BUCKAROO_EMAESTRO_ENABLED',
                ),
                array(
                    'type' => 'hidearea_start',
                ),
                array(
                    'type' => 'mode',
                    'name' => 'BUCKAROO_EMAESTRO_TEST',
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Frontend label'),
                    'name'     => 'BUCKAROO_EMAESTRD_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_EMAESTRD_FEE',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
                array(
                    'type' => 'hidearea_end',
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
                    'name'     => 'BUCKAROO_SOFORTBANKIN_LABEL',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->module->l('Buckaroo Fee'),
                    'name'     => 'BUCKAROO_SOFORTBANKIN_FEE',
                    'size'     => 80,
                ),
                array(
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
                array(
                    'type' => 'hidearea_end',
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
                    'smalltext' => $this->module->l(
                        'This is only for display purposes, to be able to use it in email templates.'
                    ),
                    'size'      => 4,
                    'required'  => true,
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_TRANSFER_SENDMAIL',
                    'label'     => $this->module->l('Send payment email'),
                    'smalltext' => $this->module->l(
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
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
                array(
                    'type' => 'hidearea_end',
                ),
            ),
        );
        $tx      = Tax::getTaxes(Configuration::get('PS_LANG_DEFAULT'));
        $taxes   = array();
        $taxes[] = "No tax";
        foreach ($tx as $t) {
            $taxes[$t["id_tax"]] = $t["name"];
        }
        $taxvalues = Configuration::get('BUCKAROO_AFTERPAY_TAXRATE');
        if (empty($taxvalues)) {
            $taxvalues = array();
        } else {
            $taxvalues = unserialize($taxvalues);
        }
        $fields_form[$i++] = array(
            'legend'  => $this->module->l('AfterPay Settings'),
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
                    'name'      => 'BUCKAROO_AFTERPAY_SERVISS_NAME',
                    'label'     => $this->module->l('Select afterpay service'),
                    'smalltext' => $this->module->l('Please select the service'),
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('Offer customer to pay afterwards by SEPA Direct Debit.'),
                            'value' => 'afterpayacceptgiro',
                        ),
                        array(
                            'text'  => $this->module->l('Offer customer to pay afterwards by digital invoice.'),
                            'value' => 'afterpaydigiaccept',
                        ),
                        array(
                            'text'  => $this->module->l('Both are enabled'),
                            'value' => 'both',
                        ),
                    ),
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_AFTERPAY_BTB',
                    'label'     => $this->module->l('Enable AfterPay B2B'),
                    'smalltext' => $this->module->l(
                        'Digital invoice service may provide B2B payment. If you have subscription for it you can enable B2B'//phpcs:ignore
                    ),
                    'options'   => array(
                        array(
                            'text'  => $this->module->l('Disable'),
                            'value' => 'disable',
                        ),
                        array(
                            'text'  => $this->module->l('Enable'),
                            'value' => 'enable',
                        ),
                    ),
                ),
                array(
                    'type'      => 'select',
                    'name'      => 'BUCKAROO_AFTERPAY_DEFAULT_VAT',
                    'label'     => $this->module->l('Default product Vat type'),
                    'smalltext' => $this->module->l('Please select default vat type for your products'),
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
                    'smalltext' => $this->module->l('Please select  vat type for wrapping'),
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
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $this->module->l('Save configuration'),
                    'required' => true,
                ),
                array(
                    'type' => 'hidearea_end',
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
}
