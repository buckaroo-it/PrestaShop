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
use Buckaroo\PrestaShop\Classes\JWTAuth;

class BaseApiController extends ModuleFrontController
{
    protected $jwt;

    public $module;

    public function __construct()
    {
        parent::__construct();
        $this->jwt = new JWTAuth();
    }

    protected function authenticate()
    {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

        if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
            $this->sendErrorResponse('Unauthorized', 401);
        }

        $token = substr($authHeader, 7);

        if (!$this->jwt->decode($token)) {
            $this->sendErrorResponse('Invalid token', 403);
        }
    }

    protected function sendResponse($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        $this->ajaxRender(json_encode($data));
    }

    protected function sendErrorResponse($message, $status = 400)
    {
        $this->sendResponse(['error' => $message], $status);
    }

    protected function getJsonInput()
    {
        $rawData = \Tools::file_get_contents('php://input');

        return json_decode($rawData, true);
    }
}
