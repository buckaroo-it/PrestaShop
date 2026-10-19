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

use Buckaroo\PrestaShop\Src\Klarna\ReservationService;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminKlarnaReservationController extends FrameworkBundleAdminController
{
    /**
     * @var ReservationService
     */
    private $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    public function cancelReservation(Request $request): JsonResponse
    {
        return $this->handle($request, 'cancel');
    }

    public function extendReservation(Request $request): JsonResponse
    {
        return $this->handle($request, 'extend');
    }

    private function handle(Request $request, string $action): JsonResponse
    {
        $order = $this->order($request);
        if (!$order instanceof \Order) {
            return $this->renderError('Order not found.');
        }

        try {
            $message = $action === 'cancel'
                ? $this->reservationService->cancel($order)
                : $this->reservationService->extend($order);

            $this->addFlash('success', $message);

            return new JsonResponse(['error' => false, 'message' => $message]);
        } catch (\Throwable $th) {
            return $this->renderError($this->exceptionMessage($th));
        }
    }

    /**
     * @return \Order|null
     */
    private function order(Request $request)
    {
        $orderId = $request->get('orderId');
        if (!is_scalar($orderId) || (int) $orderId <= 0) {
            return null;
        }

        $order = new \Order((int) $orderId);
        if (!\Validate::isLoadedObject($order) || $order->module !== 'buckaroo3') {
            return null;
        }

        return $order;
    }

    private function renderError(string $message): JsonResponse
    {
        $this->addFlash('error', $message);

        return new JsonResponse(['error' => true, 'message' => $message]);
    }

    private function exceptionMessage(\Throwable $th): string
    {
        $message = trim($th->getMessage());
        if ($message !== '') {
            return $message;
        }

        return 'The Klarna reservation request could not be completed.';
    }
}
