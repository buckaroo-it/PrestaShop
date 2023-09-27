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
 */function autoload($payment_method)
{
    require_once _PS_ROOT_DIR_ . '/modules/buckaroo3/vendor/autoload.php';
    $class_name = Tools::strtolower($payment_method);
    $path = dirname(__FILE__) . "/{$class_name}/{$class_name}.php";
    if (file_exists($path)) {
        require_once $path;
    } else {
        exit('Class not found!');
    }
}

function initials($str)
{
    $ret = '';
    foreach (explode(' ', $str) as $word) {
        $ret .= Tools::strtoupper($word[0]) . '.';
    }

    return $ret;
}
