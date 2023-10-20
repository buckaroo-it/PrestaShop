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
class Product extends ProductCore
{
    public function __construct(
        $id_product = null,
        $full = false,
        $id_lang = null,
        $id_shop = null,
        Context $context = null
    ) {
        parent::__construct($id_product, $full, $id_lang, $id_shop, $context);
    }
}
