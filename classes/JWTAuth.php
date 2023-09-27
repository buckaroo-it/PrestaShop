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

namespace Buckaroo\PrestaShop\Classes;

require_once _PS_MODULE_DIR_ . 'buckaroo3/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTAuth
{
    private $key = 'your_super_secret_key';  // Should be stored securely

    public function encode($data)
    {
        return JWT::encode($data, $this->key, 'HS256');  // Added 'HS256' as the algorithm
    }

    public function decode($token)
    {
        return JWT::decode($token, new Key($this->key, 'HS256'));
    }
}
