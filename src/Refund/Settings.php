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

namespace Buckaroo\Prestashop\Refund;

use Configuration;
use Tools;

class Settings
{
    public const LABEL_REFUND_RESTOCK = "BUCKAROO_REFUND_RESTOCK";
    public const LABEL_REFUND_CREDIT_SLIP = "BUCKAROO_REFUND_CREDIT_SLIP";
    public const LABEL_REFUND_VOUCHER = "BUCKAROO_REFUND_VOUCHER";
    public const LABEL_REFUND_CREATE_NEGATIVE_PAYMENT = "BUCKAROO_REFUND_CREATE_NEGATIVE_PAYMENT";

    public function getFormFields($module): array
    {
        if(!method_exists($module, 'l')) {
            throw new \Exception("Cannot find module translator", 1);
        }

        return [
            'legend'  => $module->l('Refund settings'),
            'name'    => 'REFUND',
            'position' => 0.5,
            'enabled' => true,
            'input'   => [
                [
                    'type' => 'bool',
                    'name' => self::LABEL_REFUND_RESTOCK,
                    'label' => $module->l('Re-stock products')
                ],
                [
                    'type' => 'bool',
                    'name' => self::LABEL_REFUND_CREDIT_SLIP,
                    'label' => $module->l('Generate a credit slip')
                ],
                [
                    'type' => 'bool',
                    'name' => self::LABEL_REFUND_VOUCHER,
                    'label' => $module->l('Generate a voucher')
                ],
                [
                    'type' => 'bool',
                    'name' => self::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT,
                    'label' => $module->l('Create negative payments on refund')
                ],
                [
                    'type'     => 'submit',
                    'name'     => 'save_data',
                    'label'    => $module->l('Save configuration'),
                    'required' => true,
                ]
            ],
        ];
    }

    public function getValues(): array
    {
        return [
            self::LABEL_REFUND_RESTOCK => Configuration::get(self::LABEL_REFUND_RESTOCK),
            self::LABEL_REFUND_CREDIT_SLIP => Configuration::get(self::LABEL_REFUND_CREDIT_SLIP),
            self::LABEL_REFUND_VOUCHER => Configuration::get(self::LABEL_REFUND_VOUCHER),
            self::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT => Configuration::get(self::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT),
        ];
    }

    public function install()
    {
        Configuration::updateValue(self::LABEL_REFUND_RESTOCK, false);
        Configuration::updateValue(self::LABEL_REFUND_CREDIT_SLIP, true);
        Configuration::updateValue(self::LABEL_REFUND_VOUCHER, false);
        Configuration::updateValue(self::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT, false);
    }

    public function uninstall()
    {
        Configuration::deleteByName(self::LABEL_REFUND_RESTOCK);
        Configuration::deleteByName(self::LABEL_REFUND_CREDIT_SLIP);
        Configuration::deleteByName(self::LABEL_REFUND_VOUCHER);
        Configuration::deleteByName(self::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT);
    }

    public function updateAll()
    {
        Configuration::updateValue(self::LABEL_REFUND_RESTOCK, Tools::getValue(self::LABEL_REFUND_RESTOCK));
        Configuration::updateValue(self::LABEL_REFUND_CREDIT_SLIP, Tools::getValue(self::LABEL_REFUND_CREDIT_SLIP));
        Configuration::updateValue(self::LABEL_REFUND_VOUCHER, Tools::getValue(self::LABEL_REFUND_VOUCHER));
        Configuration::updateValue(self::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT, Tools::getValue(self::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT));
    }
}
