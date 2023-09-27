<?php

use Buckaroo\Classes\JWTAuth;

class BaseApiController extends ModuleFrontController
{
    protected $jwt;

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
        $this->ajaxDie(json_encode($data));
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
