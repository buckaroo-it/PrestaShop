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

use Buckaroo\PrestaShop\Src\Entity\BkOrdering;
use Doctrine\ORM\EntityManager;

class Orderings extends BaseApiController
{
    private $bkOrderingRepository;

    public function __construct(EntityManager $entityManager)
    {
        parent::__construct();
        $this->bkOrderingRepository = $entityManager->getRepository(BkOrdering::class);
    }

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
        $countryCode = \Tools::getValue('country');
        $countryCode = !empty($countryCode) ? $countryCode : null;

        $ordering = $this->bkOrderingRepository->getOrdering($countryCode);

        return $this->sendResponse([
            'status' => true,
            'orderings' => $ordering,
        ]);
    }

    private function handlePost()
    {
        $data = $this->getJsonInput();

        $countryId = $this->getValueOrNull($data, 'country_id');
        $value = $this->getValueOrNull($data, 'value');

        if (!$value) {
            return $this->sendResponse([
                'status' => false,
                'message' => 'Missing or invalid data',
            ]);
        }

        $result = $this->bkOrderingRepository->updateOrdering(json_encode($value), $countryId);

        return $this->sendResponse(['status' => $result]);
    }

    private function getValueOrNull(array $data, $key)
    {
        return isset($data[$key]) && !empty($data[$key]) ? $data[$key] : null;
    }
}
