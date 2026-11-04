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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Resolves the human readable payment label for a Buckaroo push/return response.
 *
 * Historically this logic overwrote Module::$displayName from the module
 * constructor. The module name must stay stable (it is what PrestaShop shows in
 * the Module Manager and writes to config.xml), so the label is now computed on
 * demand and returned instead of being stored on the module.
 */
final class PaymentLabelResolver
{
    public const GROUP_TRANSACTION_TYPE = 'I150';
    public const GROUP_TRANSACTION_LABEL = 'Group transaction';

    /**
     * @var object|null Repository exposing getPaymentMethodsLabel(string)
     */
    private $paymentMethodRepository;

    /**
     * @param object|null $paymentMethodRepository
     */
    public function __construct($paymentMethodRepository = null)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * @param object|null $response Buckaroo response (isValid(), hasSucceeded(), payment_method, status, brq_transaction_type)
     * @param string $defaultLabel Label returned when the response carries no usable method
     */
    public function resolve($response, string $defaultLabel): string
    {
        if (!is_object($response) || !method_exists($response, 'isValid') || !$response->isValid()) {
            return $defaultLabel;
        }

        if (isset($response->brq_transaction_type) && $response->brq_transaction_type === self::GROUP_TRANSACTION_TYPE) {
            return self::GROUP_TRANSACTION_LABEL;
        }

        $paymentMethod = isset($response->payment_method) ? (string) $response->payment_method : '';

        if (method_exists($response, 'hasSucceeded') && $response->hasSucceeded()) {
            return $paymentMethod !== '' ? $paymentMethod : $defaultLabel;
        }

        if (!empty($response->status) && $paymentMethod !== '') {
            $label = $this->lookupLabel($paymentMethod);
            if ($label !== '') {
                return $label;
            }
        }

        return $defaultLabel;
    }

    private function lookupLabel(string $paymentMethod): string
    {
        if (!is_object($this->paymentMethodRepository) || !method_exists($this->paymentMethodRepository, 'getPaymentMethodsLabel')) {
            return '';
        }

        try {
            $label = $this->paymentMethodRepository->getPaymentMethodsLabel($paymentMethod);
        } catch (\Throwable $exception) {
            return '';
        }

        return is_string($label) ? $label : '';
    }
}
