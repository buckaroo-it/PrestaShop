<?php
/**
 * Test doubles shared by the refund and admin controller tests.
 *
 * Nothing here replaces a module class: these are small doubles for the
 * PrestaShop/Symfony collaborators the refund code receives through its
 * constructors, plus the PS_TAX_* constants the core defines.
 */

namespace BuckarooTest\Unit\RefundAdmin;

use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssuePartialRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssueStandardRefundCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

// PrestaShop core constants (config/defines.inc.php).
if (!defined('PS_TAX_EXC')) {
    define('PS_TAX_EXC', 1);
}
if (!defined('PS_TAX_INC')) {
    define('PS_TAX_INC', 0);
}

/** Mirrors PrestaShop's OrderId value object. */
class OrderIdValue
{
    /** @var int */
    private $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }
}

/** IssuePartialRefundCommand with the voucher amount getter of the core command. */
class PartialRefundCommand extends IssuePartialRefundCommand
{
    /** @var mixed */
    public $voucherRefundAmount;

    public function getVoucherRefundAmount()
    {
        return $this->voucherRefundAmount;
    }
}

/** IssueStandardRefundCommand with the voucher amount getter of the core command. */
class StandardRefundCommand extends IssueStandardRefundCommand
{
    public function getVoucherRefundAmount()
    {
        return null;
    }
}

/**
 * A standard refund command that is not an IssuePartialRefundCommand (in the
 * core both commands are unrelated; the shared stub makes one extend the other).
 */
class DetachedStandardRefundCommand
{
    /** @var int */
    private $orderId;

    /** @var bool */
    private $refundShipping;

    public function __construct(int $orderId, bool $refundShipping)
    {
        $this->orderId = $orderId;
        $this->refundShipping = $refundShipping;
    }

    public function getOrderId()
    {
        return new OrderIdValue($this->orderId);
    }

    public function refundShippingCost()
    {
        return $this->refundShipping;
    }

    public function getOrderDetailRefunds()
    {
        return [7 => ['quantity' => 1]];
    }

    public function getVoucherRefundType()
    {
        return 0;
    }

    public function getVoucherRefundAmount()
    {
        return null;
    }
}

/** Repository double: returns fixed rows and records the criteria it was asked for. */
class RecordingRepository
{
    /** @var array<int, object> */
    public $rows = [];

    /** @var array<int, array> */
    public $criteria = [];

    /** @var object|null */
    public $one;

    public function __construct(array $rows = [], $one = null)
    {
        $this->rows = $rows;
        $this->one = $one;
    }

    public function findBy(array $criteria, ?array $orderBy = null)
    {
        $this->criteria[] = [$criteria, $orderBy];

        return $this->rows;
    }

    public function findOneBy(array $criteria)
    {
        $this->criteria[] = [$criteria, null];

        return $this->one;
    }
}

/** EntityManager double whose repository and flush arguments tests can inspect. */
class RecordingEntityManager extends \Doctrine\ORM\EntityManager
{
    /** @var object|null */
    public $repository;

    /** @var array<int, string> */
    public $repositoryRequests = [];

    /** @var array<int, array> */
    public $flushArgs = [];

    public function __construct($repository = null)
    {
        $this->repository = $repository;
    }

    public function getRepository($className)
    {
        $this->repositoryRequests[] = $className;

        return $this->repository;
    }

    public function flush()
    {
        $this->flushArgs[] = func_get_args();
        parent::flush();
    }
}

/** In-memory session. */
class ArraySession implements \Symfony\Component\HttpFoundation\Session\SessionInterface
{
    /** @var array<string, mixed> */
    public $values = [];

    /** @var array<int, array> */
    public $log = [];

    public function get($name, $default = null)
    {
        return array_key_exists($name, $this->values) ? $this->values[$name] : $default;
    }

    public function set($name, $value)
    {
        $this->log[] = ['set', $name, $value];
        $this->values[$name] = $value;
    }

    public function has($name)
    {
        return array_key_exists($name, $this->values);
    }

    public function remove($name)
    {
        $this->log[] = ['remove', $name];
        $value = $this->get($name);
        unset($this->values[$name]);

        return $value;
    }
}

/** Command bus double: records the commands and the session flag seen while handling. */
class RecordingCommandBus implements CommandBusInterface
{
    /** @var array<int, object> */
    public $commands = [];

    /** @var array<int, mixed> */
    public $skipFlagDuringHandle = [];

    /** @var ArraySession|null */
    public $session;

    /** @var \Throwable|null */
    public $throw;

    public function handle($command)
    {
        $this->commands[] = $command;
        $this->skipFlagDuringHandle[] = $this->session ? $this->session->get('buckaroo_skip_refund') : null;
        if ($this->throw) {
            throw $this->throw;
        }
    }
}

/** Service container double (Symfony service locator). */
class ServiceContainer implements ContainerInterface
{
    /** @var array<string, mixed> */
    public $services = [];

    /** @var array<string, \Throwable> */
    public $failing = [];

    public function __construct(array $services = [])
    {
        $this->services = $services;
    }

    public function get($id, $invalidBehavior = 1)
    {
        if (isset($this->failing[$id])) {
            throw $this->failing[$id];
        }

        return $this->services[$id] ?? null;
    }

    public function has($id)
    {
        return array_key_exists($id, $this->services) || isset($this->failing[$id]);
    }
}

/** CSRF token manager double. */
class TokenManager implements CsrfTokenManagerInterface
{
    /** @var string */
    public $name;

    public function __construct(string $name = 'manager')
    {
        $this->name = $name;
    }

    public function getToken($tokenId)
    {
        return new CsrfToken($tokenId, 'token-' . $tokenId);
    }

    public function refreshToken($tokenId)
    {
        return $this->getToken($tokenId);
    }

    public function removeToken($tokenId)
    {
        return null;
    }

    public function isTokenValid(CsrfToken $token)
    {
        return $token->getValue() === 'token-' . $token->getId();
    }
}

/** Shape of a Buckaroo push/return response as KlarnaTransactionKey reads it. */
#[\AllowDynamicProperties]
class PushResponse
{
    /** @var bool */
    public $succeeded = true;

    public function __construct(array $props = [], bool $succeeded = true)
    {
        foreach ($props as $key => $value) {
            $this->{$key} = $value;
        }
        $this->succeeded = $succeeded;
    }

    public function hasSucceeded()
    {
        return $this->succeeded;
    }
}
