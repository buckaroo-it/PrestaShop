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
 * @author    Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Buckaroo\PrestaShop\Src\Repository;

use Buckaroo\PrestaShop\Src\Entity\BkGiftcards;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Context;


if (!defined('_PS_VERSION_')) {
    exit;
}

class GiftCardsRepository extends EntityRepository
{

    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
    }

    public function createGiftCard($name, $code, $logo)
    {
        $giftCard = new BkGiftcards();
        $giftCard->setName($name);
        $giftCard->setCode($code);
        $giftCard->setLogo($logo);
        $giftCard->setIsCustom(1);
        $giftCard->setCreatedAt(new \DateTime());

        $this->_em->persist($giftCard);
        $this->_em->flush();

        return $giftCard->getAll();
    }

    public function editGiftCard($id, $name, $code, $logo)
    {
        $giftCard = $this->find($id);

        if (!$giftCard) {
            return $this->createGiftCard($name, $code, $logo);
        }

        $giftCard->setName($name);
        $giftCard->setCode($code);
        $giftCard->setLogo($logo);

        $this->_em->flush();

        return $giftCard->getAll();
    }

    public function getGiftCards(bool $isCustom)
    {
        if ($isCustom) {
            $giftCards = $this->findBy(['is_custom' => 1]);
        } else {
            $giftCards = $this->findBy(['is_custom' => 0]);
        }

        $formattedGiftCards = [];
        foreach ($giftCards as $giftCard) {
            $formattedGiftCards[] = [
                'id' => $giftCard->getId(),
                'code' => $giftCard->getCode(),
                'name' => $giftCard->getName(),
                'logo' => $giftCard->getLogo(),
                'is_custom' => $giftCard->getIsCustom(),
                'created_at' => $giftCard->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $formattedGiftCards;

    }

    public function removeGiftCard($id)
    {
        $giftCard = $this->find($id);

        if (!$giftCard) {
            throw new \Exception("Gift card not found with ID: $id");
        }

        $this->_em->remove($giftCard);
        $this->_em->flush();

        return [
            'status' => true,
        ];
    }


}