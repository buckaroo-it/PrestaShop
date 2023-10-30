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

namespace Buckaroo\PrestaShop\Src\Config;

class Config
{
    public const BUCKAROO_TEST = 'BUCKAROO_TEST';
    public const BUCKAROO_MERCHANT_KEY = 'BUCKAROO_MERCHANT_KEY';
    public const BUCKAROO_SECRET_KEY = 'BUCKAROO_SECRET_KEY';
    public const BUCKAROO_TRANSACTION_LABEL = 'BUCKAROO_TRANSACTION_LABEL';
    public const BUCKAROO_TRANSACTION_FEE = 'BUCKAROO_TRANSACTION_FEE';

    public const LABEL_REFUND_RESTOCK = 'BUCKAROO_REFUND_RESTOCK';
    public const LABEL_REFUND_CREDIT_SLIP = 'BUCKAROO_REFUND_CREDIT_SLIP';
    public const LABEL_REFUND_VOUCHER = 'BUCKAROO_REFUND_VOUCHER';
    public const LABEL_REFUND_CREATE_NEGATIVE_PAYMENT = 'BUCKAROO_REFUND_CREATE_NEGATIVE_PAYMENT';

    public const FILE_NAME = 'Installer';
}
