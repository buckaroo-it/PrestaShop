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

namespace Buckaroo\PrestaShop\Src\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class RawCreditCardsRepository
{
    /**
     * @throws \Exception
     */
    public function insertCreditCards()
    {
        $this->clearCreditCardsTable();
        $creditCardsData = $this->getCreditCardsData();

        foreach ($creditCardsData as $cardData) {
            $this->insertCreditCard($cardData);
        }

        return $creditCardsData;
    }

    private function insertCreditCard(array $methodData): void
    {
        $data = [
            'name' => pSQL($methodData['name']),
            'service_code' => pSQL($methodData['service_code']),
            'icon' => pSQL($methodData['icon']),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!\Db::getInstance()->insert('bk_creditcards', $data)) {
            throw new \Exception('Database error: Could not insert Credit cards');
        }
    }

    public function getCreditCardsData()
    {
        return [
            ['name' => 'American Express', 'service_code' => 'amex', 'icon' => 'AMEX.svg'],
            ['name' => 'CarteBancaire', 'service_code' => 'cartebancaire', 'icon' => 'CarteBancaire.svg'],
            ['name' => 'CarteBleue', 'service_code' => 'cartebleue', 'icon' => 'CarteBleue.svg'],
            ['name' => 'Dankort', 'service_code' => 'dankort', 'icon' => 'Dankort.svg'],
            ['name' => 'Maestro', 'service_code' => 'maestro', 'icon' => 'Maestro.svg'],
            ['name' => 'Mastercard', 'service_code' => 'mastercard', 'icon' => 'MasterCard.svg'],
            ['name' => 'Nexi', 'service_code' => 'nexi', 'icon' => 'Nexi.svg'],
            ['name' => 'PostePay', 'service_code' => 'postepay', 'icon' => 'Postepay.svg'],
            ['name' => 'VISA', 'service_code' => 'visa', 'icon' => 'Visa.svg'],
            ['name' => 'VISA Electron', 'service_code' => 'visaelectron', 'icon' => 'VisaElectron.svg'],
            ['name' => 'VPAY', 'service_code' => 'vpay', 'icon' => 'VPay.svg'],
        ];
    }

    public function getCreditCardsFromDB(): array
    {
        $query = new \DbQuery();
        $query->select('*');
        $query->from('bk_creditcards');

        $result = \Db::getInstance()->executeS($query);

        if (!$result) {
            throw new \Exception('Database error: Could not fetch credit cards');
        }

        return $result;
    }

    private function clearCreditCardsTable(): void
    {
        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'bk_creditcards';
        if (!\Db::getInstance()->execute($sql)) {
            throw new \Exception('Database error: Could not clear payment methods table');
        }
    }
}
