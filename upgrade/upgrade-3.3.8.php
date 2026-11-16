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

    // 3.3.8 added iDIN columns to the core customer and product tables and
    // installed a Product override. iDIN data lives in the module's own
    // bk_customer_idin and bk_product_idin tables since 4.x (see
    // upgrade-4.0.0.php), so neither step is needed on the way up any more;
    // Marketplace modules must not alter core tables, and the override file
    // no longer ships. Any columns left behind by an old installation are
    // dropped by Src\Install\IdinColumnsRemover when they exist.

    return true;
}
