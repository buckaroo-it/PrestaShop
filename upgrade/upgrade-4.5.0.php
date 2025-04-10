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

use Buckaroo\PrestaShop\Src\Repository\RawOrderingRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @return mixed
 * @throws Exception
 */
function upgrade_module_4_5_0($object)
{
        Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'bk_payment_methods WHERE name = "giropay"');
        $orderingRepository = new RawOrderingRepository();
        $orderingRepository->insertCountryOrdering();
        return true;
}
