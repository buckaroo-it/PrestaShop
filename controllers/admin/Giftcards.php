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

namespace Buckaroo\PrestaShop\Controllers\admin;

use Buckaroo\PrestaShop\Src\Entity\BkGiftcards;
use Doctrine\ORM\EntityManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Giftcards extends BaseApiController
{
    private $bkGiftCardsRepository;

    public function __construct(EntityManager $entityManager)
    {
        parent::__construct();
        $this->bkGiftCardsRepository = $entityManager->getRepository(BkGiftcards::class);
    }

    /**
     * @throws \Exception
     */
    public function initContent()
    {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                return $this->handleGet();
            case 'POST':
                return $this->handlePost();
        }

    }

    private function handleGet()
    {
        $giftcards = $this->bkGiftCardsRepository->getGiftCards(false);
        $customGiftcards = $this->bkGiftCardsRepository->getGiftCards(true);

        $data = [
            'status' => true,
            'giftcards' => $giftcards,
            'custom_giftcards' => $customGiftcards,
        ];

        return $this->sendResponse($data);
    }

    private function handlePost()
    {
        $data = $this->getJsonInput();

        $name = $this->getValueOrNull($data, 'name');
        $code = $this->getValueOrNull($data, 'service_code');
        $logo = $this->getValueOrNull($data, 'logo_url');

        if (!($name || $code || $logo)) {
            return $this->sendResponse([
                'status' => false,
                'message' => 'Missing or invalid data',
            ]);
        }

        $result = $this->bkGiftCardsRepository->createGiftCard($name, $code, $logo);
        $data = [
            'status' => true,
            'custom_giftcard' => $result,
        ];

        return $this->sendResponse($data);
    }

    public function editGiftCard()
    {
        $data = $this->getJsonInput();

        $id = $this->getValueOrNull($data, 'id');
        $name = $this->getValueOrNull($data, 'name');
        $code = $this->getValueOrNull($data, 'service_code');
        $logo = $this->getValueOrNull($data, 'logo_url');

        if (!($name || $code || $logo)) {
            return $this->sendResponse([
                'status' => false,
                'message' => 'Missing or invalid data',
            ]);
        }

        $result = $this->bkGiftCardsRepository->editGiftCard($id, $name, $code, $logo);
        $data = [
            'status' => true,
            'custom_giftcard' => $result,
        ];

        return $this->sendResponse($data);
    }

    public function removeGiftCard()
    {
        $data = $this->getJsonInput();

        $id = $this->getValueOrNull($data, 'id');

        if (!$id) {
            return $this->sendResponse([
                'status' => false,
                'message' => 'Missing or invalid ID',
            ]);
        }

        $result = $this->bkGiftCardsRepository->removeGiftCard($id);

        if ($result === false) {
            return $this->sendResponse([
                'status' => false,
                'message' => "Failed to delete gift card with ID: $id",
            ]);
        }

        return $this->sendResponse([
            'status' => true,
            'message' => "Gift card with ID: $id has been successfully deleted.",
        ]);
    }

    private function getValueOrNull(array $data, $key)
    {
        return isset($data[$key]) && !empty($data[$key]) ? $data[$key] : null;
    }
}
