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

use Buckaroo\PrestaShop\Src\Repository\RawCreditCardsRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Creditcards extends BaseApiController
{
    private RawCreditCardsRepository $creditCardsRepository;

    public function __construct()
    {
        parent::__construct();
        $this->creditCardsRepository = new RawCreditCardsRepository();
    }

    /**
     * @throws \Exception
     */
    public function initContent()
    {
        $creditcards = $this->creditCardsRepository->getCreditCardsFromDB();

        $data = [
            'status' => true,
            'creditcards' => $creditcards,
        ];

        return $this->sendResponse($data);
    }
}
