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


/**
 * @return mixed
 */
function upgrade_module_3_3_8($object)
{
    $object->registerHook('displayBeforeCarrier');
    
    $object->registerHook('additionalCustomerFormFields');
    $object->registerHook('actionSubmitAccountBefore');
    $object->registerHook('actionAdminCustomersListingFieldsModifier');

    $object->registerHook('displayAdminProductsMainStepLeftColumnMiddle');
    $object->registerHook('displayProductExtraContent');

    Db::getInstance()->execute("ALTER TABLE `" . _DB_PREFIX_ . "customer` 
        ADD buckaroo_idin_consumerbin VARCHAR(255) NULL, ADD buckaroo_idin_iseighteenorolder VARCHAR(255) NULL;");

    Db::getInstance()->execute("ALTER TABLE `" . _DB_PREFIX_ . "product` 
        ADD buckaroo_idin TINYINT(1) NULL;");
    copy(
        _PS_ROOT_DIR_."/modules/buckaroo3/classes/Product.php",
        _PS_ROOT_DIR_."/override/classes/Product.php"
    );

    return true;
}
