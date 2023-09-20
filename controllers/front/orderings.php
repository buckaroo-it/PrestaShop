<?php
include_once dirname(__FILE__) . '/BaseApiController.php';


class Buckaroo3OrderingsModuleFrontController extends BaseApiController
{
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

        $data = [
            "status" => true,
            "orderings" => [
                "id" => 1,
                "country_id" => null,
                "value" => [
                    [
                        "id" => 4,
                        "name" => "ideal",
                        "icon" => "iDEAL.svg"
                    ],
                    [
                        "id" => 6,
                        "name" => "bancontact",
                        "icon" => "Bancontact.svg"
                    ],
                    [
                        "id" => 2,
                        "name" => "creditcard",
                        "icon" => "Creditcards.svg"
                    ],
                    [
                        "id" => 5,
                        "name" => "paypal",
                        "icon" => "PayPal.svg"
                    ],
                    [
                        "id" => 7,
                        "name" => "sofort",
                        "icon" => "Sofort.svg"
                    ],
                    [
                        "id" => 3,
                        "name" => "giftcard",
                        "icon" => "Giftcards.svg"
                    ],
                    [
                        "id" => 8,
                        "name" => "giropay",
                        "icon" => "Giropay.svg"
                    ],
                    [
                        "id" => 9,
                        "name" => "payconiq",
                        "icon" => "Payconiq.svg"
                    ],
                    [
                        "id" => 11,
                        "name" => "afterpay",
                        "icon" => "AfterPay.svg"
                    ],
                    [
                        "id" => 10,
                        "name" => "klarnakp",
                        "icon" => "Klarna.svg"
                    ],
                    [
                        "id" => 12,
                        "name" => "trustly",
                        "icon" => "Trustly.svg"
                    ],
                    [
                        "id" => 13,
                        "name" => "belfius",
                        "icon" => "Belfius.svg"
                    ],
                    [
                        "id" => 1,
                        "name" => "applepay",
                        "icon" => "ApplePay.svg"
                    ]
                ],
                "created_at" => "2023-04-20T11=>22=>17.000000Z",
                "updated_at" => "2023-06-21T13=>02=>39.000000Z"
            ]
        ];

        $this->sendResponse($data);
    }

    private function handlePost()
    {
        $data = $this->getJsonInput();

        // Send response instead of var_dump
        $response = [
            "status" => true,
            "message" => "Data received successfully",
            "received_data" => $data
        ];
        $this->sendResponse($response);
    }

}