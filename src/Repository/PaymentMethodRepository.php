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

use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Doctrine\ORM\EntityRepository;

class PaymentMethodRepository extends EntityRepository implements BkPaymentMethodRepositoryInterface
{
    public function fetchMethodsFromDBWithConfig(int $isPaymentMethod): array
    {
        $qb = $this->_em->createQueryBuilder();

        $qb->select('pm.name AS payment_name', 'pm.icon AS payment_icon', 'config.value AS config_value')
            ->from(BkPaymentMethods::class, 'pm')
            ->where('pm.is_payment_method = :isPaymentMethod')
            ->setParameter('isPaymentMethod', $isPaymentMethod)
            ->leftJoin(BkConfiguration::class, 'config', 'WITH', 'pm.id = config.configurable_id');

        $results = $qb->getQuery()->getArrayResult();

        if (!$results) {
            throw new \Exception('Database error: Could not fetch payment methods with config');
        }

        $capayableIn3 = \Module::getInstanceByName('buckaroo3')->get('buckaroo.classes.issuers.capayableIn3');

        $payments = [];
        foreach ($results as $result) {
            $payment = [
                'name' => $result['payment_name'],
                'icon' => $result['payment_icon'],
            ];

            if ($result['payment_name'] === 'in3') {
                $payment['icon'] = $capayableIn3->getLogo();
            }

            if (isset($result['config_value']) && ($configArray = json_decode($result['config_value'], true)) !== null) {
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payment = array_merge($payment, $configArray);
                } else {
                    throw new \Exception('JSON decode error: ' . json_last_error_msg());
                }
            }

            $payments[] = $payment;
        }

        return $payments;
    }
}
