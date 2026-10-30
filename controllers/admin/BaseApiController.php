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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Base class of the configuration API controllers used by the Vue dashboard.
 *
 * Extends Symfony's AbstractController directly: PrestaShop's
 * FrameworkBundleAdminController is deprecated in 9.x and its replacement
 * (PrestaShopAdminController) does not exist on 1.7.8/8.x. The services this
 * controller needs are declared through getSubscribedServices(), which is the
 * supported way to reach container services from a Symfony controller on
 * every PrestaShop version (Symfony 4.4 up to 6.4).
 */
class BaseApiController extends AbstractController
{
    private const CSRF_TOKEN_MANAGER_SERVICES = [
        'security.csrf.token_manager',
        'buckaroo.csrf.token_manager',
    ];

    /**
     * @return array<string, string>
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();
        foreach (self::CSRF_TOKEN_MANAGER_SERVICES as $id) {
            // Optional ("?"): the controller keeps working when a service is not defined.
            $services[$id] = '?' . CsrfTokenManagerInterface::class;
        }

        return $services;
    }

    protected function getCsrfTokenManager(): ?CsrfTokenManagerInterface
    {
        foreach (self::CSRF_TOKEN_MANAGER_SERVICES as $id) {
            try {
                // $this->container is the service locator Symfony injects; when the
                // controller was not wired as a service subscriber it is unset (typed
                // property on Symfony 6) or null (Symfony 3/4) and the access throws.
                if ($this->container->has($id)) {
                    $manager = $this->container->get($id);
                    if ($manager instanceof CsrfTokenManagerInterface) {
                        return $manager;
                    }
                }
            } catch (\Throwable $e) {
                // Service not available in this container; try the next candidate.
            }
        }

        return null;
    }

    protected function sendResponse($data, $status = 200)
    {
        return new JsonResponse($data, $status);
    }

    protected function sendErrorResponse($message, $status = 400)
    {
        return $this->sendResponse(['error' => $message], $status);
    }

    protected function getJsonInput(): array
    {
        $rawData = \Tools::file_get_contents('php://input');

        if (empty($rawData)) {
            return [];
        }

        $data = json_decode($rawData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON input: ' . json_last_error_msg());
        }

        return $data ?? [];
    }
}
