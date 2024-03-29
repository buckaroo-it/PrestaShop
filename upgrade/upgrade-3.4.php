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

// Remove old payment methods
// empayment
Configuration::deleteByName('BUCKAROO_EMPAYMENT_ENABLED');
Configuration::deleteByName('BUCKAROO_EMPAYMENT_TEST');
Configuration::deleteByName('BUCKAROO_EMPAYMENT_LABEL');
Configuration::deleteByName('BUCKAROO_EMPAYMENT_FEE');

// directdebit
Configuration::deleteByName('BUCKAROO_DD_ENABLED');
Configuration::deleteByName('BUCKAROO_DD_TEST');
Configuration::deleteByName('BUCKAROO_DD_LABEL');
Configuration::deleteByName('BUCKAROO_DD_FEE');
Configuration::deleteByName('BUCKAROO_DD_USECREDITMANAGMENT');
Configuration::deleteByName('BUCKAROO_DD_INVOICEDELAY');
Configuration::deleteByName('BUCKAROO_DD_DATEDUE');
Configuration::deleteByName('BUCKAROO_DD_MAXREMINDERLEVEL');

// Remove certificate, pushurl, culture configs
Configuration::deleteByName('BUCKAROO_CERTIFICATE');
Configuration::deleteByName('BUCKAROO_CERTIFICATE_FILE');
Configuration::deleteByName('BUCKAROO_CERTIFICATE_THUMBPRINT');
Configuration::deleteByName('BUCKAROO_TRANSACTION_RETURNURL');
Configuration::deleteByName('BUCKAROO_TRANSACTION_CULTURE');

// Rename Capayable to In3 configs
Configuration::updateValue('BUCKAROO_IN3_ENABLED', Configuration::get('BUCKAROO_CAPAYABLE_ENABLED'));
Configuration::updateValue('BUCKAROO_IN3_TEST', Configuration::get('BUCKAROO_CAPAYABLE_TEST'));
Configuration::updateValue('BUCKAROO_IN3_LABEL', Configuration::get('BUCKAROO_CAPAYABLE_LABEL'));
Configuration::updateValue('BUCKAROO_IN3_FEE', Configuration::get('BUCKAROO_CAPAYABLE_FEE'));
// delete old references
Configuration::deleteByName('BUCKAROO_CAPAYABLE_ENABLED');
Configuration::deleteByName('BUCKAROO_CAPAYABLE_TEST');
Configuration::deleteByName('BUCKAROO_CAPAYABLE_LABEL');
Configuration::deleteByName('BUCKAROO_CAPAYABLE_FEE');

// Paypal update
Configuration::updateValue('BUCKAROO_PAYPAL_FEE', Configuration::get('BUCKAROO_BUCKAROOPAYPAL_FEE'));
Configuration::deleteByName('BUCKAROO_BUCKAROOPAYPAL_FEE');
