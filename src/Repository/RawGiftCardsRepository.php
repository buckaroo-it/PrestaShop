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

class RawGiftCardsRepository
{
    /**
     * @throws \Exception
     */
    public function insertGiftCards()
    {
        $this->clearGiftCardsTable();
        $giftCardsData = $this->getGiftCardsData();

        foreach ($giftCardsData as $cardData) {
            $this->insertGiftCard($cardData);
        }

        return $giftCardsData;
    }

    private function insertGiftCard(array $methodData): void
    {
        $data = [
            'logo' => pSQL($methodData['logo']),
            'code' => pSQL($methodData['code']),
            'name' => pSQL($methodData['name']),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!\Db::getInstance()->insert('bk_giftcards', $data)) {
            throw new \Exception('Database error: Could not insert gift cards');
        }
    }

    public function getGiftCardsData()
    {
        return [
            ['logo' => 'Boekenbon.svg','code' => 'boekenbon','name' => 'Boekenbon'],
            ['logo' => 'FashionGiftcard.svg','code' => 'fashionucadeaukaart','name' => 'Fashion Giftcard'],
            ['logo' => 'FashionCheque.svg','code' => 'fashioncheque','name' => 'Fashion Cheque'],
            ['logo' => 'VVVgiftcard.svg','code' => 'vvvgiftcard','name' => 'VVV Giftcard'],
            ['logo' => 'WebshopGiftcard.svg','code' => 'webshopgiftcard','name' => 'Webshop Giftcard'],
            ['logo' => 'NationaleBioscoopBon.svg','code' => 'digitalebioscoopbon','name' => 'Nationale Bioscoopbon'],
            ['logo' => 'YourGift.svg','code' => 'yourgift','name' => 'Yourgift Card',],
        ];
    }

    public function getGiftCardsFromDB(): array
    {
        $query = new \DbQuery();
        $query->select('*');
        $query->from('bk_giftcards');

        $result = \Db::getInstance()->executeS($query);

        if (!$result) {
            throw new \Exception('Database error: Could not fetch gift cards');
        }

        return $result;
    }

    private function clearGiftCardsTable(): void
    {
        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'bk_giftcards';
        if (!\Db::getInstance()->execute($sql)) {
            throw new \Exception('Database error: Could not clear gift cards table');
        }
    }
}
