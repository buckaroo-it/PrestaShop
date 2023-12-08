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

namespace Buckaroo\PrestaShop\Classes\Issuers;

class Ideal extends Issuers
{
    protected const CACHE_ISSUERS_KEY = 'BUCKAROO_IDEAL_ISSUERS_CACHE';
    protected const CACHE_ISSUERS_DATE_KEY = 'BUCKAROO_IDEAL_ISSUERS_CACHE_DATE';

    public function __construct()
    {
        parent::__construct('ideal');
    }
}
