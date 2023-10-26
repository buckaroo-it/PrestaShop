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

namespace Buckaroo\PrestaShop\Src\Service;

use Doctrine\ORM\EntityManager;
use Buckaroo\PrestaShop\Src\Entity\BkCountries;
use Buckaroo\PrestaShop\Src\Repository\BkCountriesRepositoryInterface;

class BuckarooCountriesService
{
    protected BkCountriesRepositoryInterface $countryRepository;

    public function __construct(EntityManager $entityManager)
    {
        $this->countryRepository = $entityManager->getRepository(BkCountries::class, BkCountriesRepositoryInterface::class);;
    }

    public function synchronizeCountries()
    {
        return $this->countryRepository->checkAndInsertNewCountries();
    }
}
