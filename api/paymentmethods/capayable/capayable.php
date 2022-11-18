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

class Capayable extends PaymentMethod
{
    public function __construct()
    {
        $this->type = "Capayable";
        $this->version = 1;
        $this->mode = Config::getMode($this->type);
    }

    public function pay($data = array())
    {

        $this->setCustomVar("CustomerType", "Debtor");
        $this->setCustomVar("InvoiceDate", date("d-m-Y"));

        $this->setCustomVar(
            $data['customer'],
            null,
            'Person'
        );

        $this->setCustomVar(
            $data['address'],
            null,
            'Address'
        );
        $this->setCustomVar("Phone", $data['phone'], 'Phone');
        $this->setCustomVar("Email", $data['email'], 'Email');


        $this->setArticles($data['articles']);

        return parent::executeAction('PayInInstallments');
    }


    protected function setArticles($articles)
    {
        foreach ($articles as $pos => $article) {
            $this->setCustomVarsAtPosition(
                $article,
                $pos,
                'ProductLine'
            );
        }
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
     * Set custom param at specific position in array
     *
     * @param string $key
     * @param string $value
     * @param integer $position
     * @param string $group
     * @param string $type
     *
     * @return void
     */
    public function setCustomVarAtPosition($key, $value, $position = 0, $group = null, $type = null)
    {
        if ($type === null) {
            $type = $this->type;
        }

        if (!isset($this->data['customVars'])) {
            $this->data['customVars'] = [];
        }
        if (!isset($this->data['customVars'][$type])) {
            $this->data['customVars'][$type] = [];
        }
        if ($group !== null) {
            $value = [
                "value" => $value,
                "group" => $group
            ];
        }
        $this->data['customVars'][$type][$key][$position] = $value;
    }

    /**
     * Set custom value for position fro array of values
     *
     * @param array $values
     * @param int $position
     * @param string|null $group
     * @param string|null $type
     *
     * @return void
     */
    public function setCustomVarsAtPosition($values, $position, $group = null, $type = null)
    {
        foreach ($values as $key => $value) {
            $this->setCustomVarAtPosition(
                $key, $value, $position, $group, $type
            );
        }
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

}
 