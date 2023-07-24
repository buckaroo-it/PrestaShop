<?php
/**
*
*
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

require_once(dirname(__FILE__) . '/../paymentmethod.php');

class PayPal extends PaymentMethod
{
    public function __construct()
    {
        $this->type = "paypal";
        $this->version = 1;
        $this->mode = Config::getMode($this->type);
    }

    //Seller protection payload
    public function getPayload($data)
    {
        $payload = [
            'customer' => [
                'name' => $data['customer_name'],
            ],
            'address' => [
                'street' => $data['address']['street'],
                'street2' => $data['address']['street2'],
                'city' => $data['address']['city'],
                'zipcode' => $data['address']['zipcode'],
                'country' => $data['address']['country']
            ],
            'phone' => [
                'mobile' => $data['phone']
            ],
        ];

        if($data['address']['state'] !== null) {
            $payload['address']['state'] = $data['address']['state'];
        }
        return $payload;
    }

    public function pay($customVars = array())
    {
        if (Config::get('BUCKAROO_PAYPAL_SELLER_PROTECTION_ENABLED')) {
            //Pay with Seller Protection enabled
            $this->payload = $this->getPayload($customVars);
            return parent::executeCustomPayAction('extraInfo');
        } else {
            //Regular paypal payment
            return parent::pay();
        }
    }
}
