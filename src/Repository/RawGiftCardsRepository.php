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

use Buckaroo\PrestaShop\Src\Entity\BkGiftcards;

class RawGiftCardsRepository
{
    const TABLE_NAME = 'bk_giftcards';
    /**
     * @throws \Exception
     */
    public static function insertGiftCards()
    {
        self::clearGiftCardsTable();

        $data = array_map(function ($giftcard){
            return $giftcard->toSqlData('id');
        }, self::getGiftCards());

        if (!\Db::getInstance()->insert(self::TABLE_NAME, $data)) {
            throw new \Exception('Database error: Could not insert Giftcards');
        }
    }

    /**
     * @throws \Exception
     */
    static public function insertGiftCard(BkGiftcards $giftcard): void
    {
        $giftcard->setCreatedAt();
        $giftcard->setUpdatedAt();

        if (!\Db::getInstance()->insert(self::TABLE_NAME, $giftcard->toSqlData('id'))) {
            throw new \Exception('Database error: Could not insert GiftCard');
        }
    }

    /**
     * @return BkGiftcards[]
     */
    static public function getGiftcards(array $giftcardsData = self::GiftcardsData){
        return array_map(function ($giftcard){
            return new BkGiftcards($giftcard);
        }, $giftcardsData);
    }

    /**
     * @return array[]
     */
    private const GiftcardsData = [
            ['code' => 'boekenbon', 'name' => 'Boekenbon', 'logo' => 'Boekenbon.svg'],
            ['code' => 'fashionucadeaukaart', 'name' => 'Fashion Giftcard', 'logo' => 'FashionGiftcard.svg'],
            ['code' => 'fashioncheque', 'name' => 'Fashion cheque', 'logo' => 'FashionCheque.svg'],
            ['code' => 'vvvgiftcard', 'name' => 'VVV Giftcard', 'logo' => 'VVVgiftcard.svg'],
            ['code' => 'webshopgiftcard', 'name' => 'Webshop Giftcard', 'logo' => 'WebshopGiftcard.svg'],
            ['code' => 'digitalebioscoopbon', 'name' => 'Nationale Bioscoopbon', 'logo' => 'NationaleBioscoopBon.svg'],
            ['code' => 'yourgift', 'name' => 'Yourgift Card', 'logo' => 'YourGift.svg']
    ];

    static private function clearGiftCardsTable(): void
    {
        $sql = 'DELETE FROM ' . _DB_PREFIX_ . self::TABLE_NAME;
        if (!\Db::getInstance()->execute($sql)) {
            throw new \Exception('Database error: Could not clear giftcards table');
        }
    }

    /**
     * @throws \Exception
     * @return BkGiftcards[]
     */
    static public function getGiftCardsFromDB(){

        $query = new \DbQuery();
        $query->select('*');
        $query->from(self::TABLE_NAME);

        try {
            return \Db::getInstance()->executeS($query);
        } catch (\Exception $e){
            throw new \Exception('Database error: Could not fetch giftcards');
        }
    }

    /**
     * @throws \Exception
     */
    static public function getGiftCardsByIds($ids)
    {
        if(count($ids)>0){
            $query = new \DbQuery();
            $query->select('*');
            $query->from(self::TABLE_NAME);
            $ids = implode(", ",$ids);
            $query->where("id IN ($ids)");

            try {
                return self::getGiftcards(\Db::getInstance()->executeS($query));
            } catch (\Exception $e){
                throw new \Exception('Database error: Could not fetch giftcards');
            }
        }
        return [];
    }

    /**
     * @throws \Exception
     */
    static public function deleteGiftcard(int $id){
        if(!\Db::getInstance()->delete(self::TABLE_NAME, 'id = '.$id)){
            throw new \Exception('Database error: Could not delete giftcard');
        }
    }

    /**
     * @throws \Exception
     */
    static public function updateGiftcard(BkGiftcards $giftcard){

        $giftcard->setUpdatedAt();

        if(!\Db::getInstance()->update(self::TABLE_NAME, $giftcard->toSqlData('created_at','id'), 'id = '.$giftcard->getId())){
            throw new \Exception('Database error: Could not update giftcard');
        }
    }

    /**
     * @throws \Exception
     */
    static public function getGiftcardById(int $id){
        $query = new \DbQuery();
        $query->select('*');
        $query->from(self::TABLE_NAME);
        $query->where('`id` = '.$id );

        try {
            $giftcard = \Db::getInstance()->executeS($query)[0];
            return new BkGiftcards($giftcard);
        } catch (\Exception $e){
            throw new \Exception('Database error: Could not fetch giftcard');
        }
    }
}
