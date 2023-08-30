<?php
namespace Buckaroo\Classes;

require_once _PS_MODULE_DIR_ . 'buckaroo3/vendor/autoload.php';

use \Firebase\JWT\JWT;
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