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

class BuckarooPayPal extends PaymentMethod
{
    public function __construct()
    {
        $this->type = "paypal";
        $this->version = 1;
        $this->mode = Config::getMode($this->type);
    }

    public function pay($customVars = array())
    {
        if (isset($customVars['sellerProtection']) && $customVars['sellerProtection'] === true ) {
            $this->setService('action2', 'extraInfo');
            $this->setService('version2', $this->version);

            $this->setCustomVar(
                [
                'Name'=> mb_substr($customVars['CustomerName'], 0, 32),
                'Street1'=> mb_substr($customVars['ShippingStreet'] . ' '. $customVars['ShippingHouse'], 0, 100),
                'CityName'=>mb_substr($customVars['ShippingCity'], 0, 40),
                'StateOrProvince'=>$customVars['StateOrProvince'],
                'PostalCode'=>mb_substr($customVars['ShippingPostalCode'],0, 20),
                'Country'=>$customVars['Country'],
                'AddressOverride'=>'TRUE'
                ]
            );
            
        }
        return parent::pay();
    }
    /**
     * Set custom param key and value
     *
     * @param string|array $keyOrValues Key of the value or associative array of values
     * @param mixed|null $value
     * @param string|null $group
     * @param string|null $type
     *
     * @return string $value
     */
    public function setCustomVar($keyOrValues, $value = null, $group = null)
    {
        return $this->setCustomVarOfType($keyOrValues, $value, $group);
    }

    /**
     * Set custom params for specific type
     *
     * @param string|array $key Key of the value or associative array of values
     * @param string|null $value
     * @param string|null $group
     * @param string|null $type
     *
     * @return string|array $value
     */
    public function setCustomVarOfType($keyOrValues, $value = null, $group = null, $type = null)
    {
        if ($type === null) {
            $type = $this->type;
        }

        if (!isset($this->data['customVars'])) {
            $this->data['customVars'] = [];
        }

        if ($type === false) {
            return $this->setCustomVarWithoutType($keyOrValues, $value);
        }

        if (!isset($this->data['customVars'][$type])) {
            $this->data['customVars'][$type] = [];
        }

        if (is_array($keyOrValues)) {

            if ($group !== null) {
                $keyOrValues = array_map(
                    function ($value) use ($group) {
                        return [
                            "value" => $value,
                            "group" => $group
                        ];
                    },
                    $keyOrValues
                );
            }

            $this->data['customVars'][$type] = array_merge(
                $this->data['customVars'][$type],
                $keyOrValues
            );
            return $keyOrValues;

        } else {
            if ($group !== null) {
                $value = [
                    "value" => $value,
                    "group" => $group
                ];
            }
            $this->data['customVars'][$type][$keyOrValues] = $value;
        }
        return $value;
    }
    /**
     * Set custom param without type key
     *
     * @param string|array $keyOrValues
     * @param string|null $value
     *
     * @return $value
     */
    public function setCustomVarWithoutType($keyOrValues, $value = null)
    {
        if (is_array($keyOrValues)) {
            if (!isset($this->data['customVars'])) {
                $this->data['customVars'] =  $keyOrValues;
            }
            
            $this->data['customVars'] = array_merge(
                $this->data['customVars'],
                $keyOrValues
            );
            return $keyOrValues;
        }
        $this->data['customVars'][$keyOrValues] = $value;
        return $value;
    }
    /**
     * Set service param key and value
     *
     * @param string $key
     * @param string $value
     * @param string|null $type
     *
     * @return string $value
     */
    public function setService($key, $value)
    {
        return $this->setServiceOfType($key, $value);
    }

    /**
     * Set service param for specific type
     *
     * @param string $key
     * @param string $value
     * @param string|null $type
     *
     * @return string $value
     */
    public function setServiceOfType($key, $value, $type = null)
    {
        if ($type === null) {
            $type = $this->type;
        }

        if (!isset($this->data['services'])) {
            $this->data['services'] = [];
        }

        if (!isset($this->data['services'][$type])) {
            $this->data['services'][$type] = [];
        }

        $this->data['services'][$type][$key] = $value;

        return $value;
    }
}
