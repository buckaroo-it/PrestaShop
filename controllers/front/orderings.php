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

include_once dirname(__FILE__) . '/BaseApiController.php';

use Buckaroo\Src\Repository\OrderingRepository;

class Buckaroo3OrderingsModuleFrontController extends BaseApiController
{
    private $orderingRepository;

    public function __construct()
    {
        parent::__construct();

        $this->orderingRepository = new OrderingRepository();  // Instantiate the repository
    }

    public function initContent()
    {
        parent::initContent();
        $this->authenticate();

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $this->handleGet();
                break;
            case 'POST':
                $this->handlePost();
                break;
        }
    }

    private function handleGet()
    {
        $ordering = $this->getOrdering();

        $data = [
            'status' => true,
            'orderings' => $ordering,
        ];

        $this->sendResponse($data);
    }

    private function getOrdering()
    {
        return $this->orderingRepository->getOrdering();
    }

    private function handlePost()
    {
        $data = $this->getJsonInput();

        // Send response instead of var_dump
        $response = [
            'status' => true,
            'message' => 'Data received successfully',
            'received_data' => $data,
        ];
        $this->sendResponse($response);
    }
}
