<?php
/**
 * Minimal Symfony, Doctrine and PrestaShop core (namespaced) stubs.
 *
 * Only the members the module touches are implemented. Every definition is
 * guarded, so a real implementation (when one is installed) always wins.
 * Bracketed namespaces keep everything in one file without eval().
 */

namespace Symfony\Component\HttpFoundation {
    if (!class_exists(ParameterBag::class)) {
        class ParameterBag implements \IteratorAggregate, \Countable
        {
            /** @var array<string, mixed> */
            protected $parameters;

            public function __construct(array $parameters = [])
            {
                $this->parameters = $parameters;
            }

            public function all()
            {
                return $this->parameters;
            }

            public function keys()
            {
                return array_keys($this->parameters);
            }

            public function get($key, $default = null)
            {
                return array_key_exists($key, $this->parameters) ? $this->parameters[$key] : $default;
            }

            public function set($key, $value)
            {
                $this->parameters[$key] = $value;
            }

            public function has($key)
            {
                return array_key_exists($key, $this->parameters);
            }

            public function remove($key)
            {
                unset($this->parameters[$key]);
            }

            public function getInt($key, $default = 0)
            {
                return (int) $this->get($key, $default);
            }

            public function getBoolean($key, $default = false)
            {
                return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOLEAN);
            }

            #[\ReturnTypeWillChange]
            public function getIterator()
            {
                return new \ArrayIterator($this->parameters);
            }

            #[\ReturnTypeWillChange]
            public function count()
            {
                return count($this->parameters);
            }
        }
    }

    if (!class_exists(Request::class)) {
        class Request
        {
            /** @var ParameterBag */
            public $query;

            /** @var ParameterBag */
            public $request;

            /** @var ParameterBag */
            public $attributes;

            /** @var ParameterBag */
            public $server;

            /** @var string|null */
            protected $content;

            /** @var object|null */
            protected $session;

            /** @var string */
            protected $method = 'GET';

            public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
            {
                $this->query = new ParameterBag($query);
                $this->request = new ParameterBag($request);
                $this->attributes = new ParameterBag($attributes);
                $this->server = new ParameterBag($server);
                $this->content = $content;
            }

            public static function createFromGlobals()
            {
                return new static($_GET, $_POST, [], $_COOKIE, [], $_SERVER);
            }

            public function get($key, $default = null)
            {
                if ($this->attributes->has($key)) {
                    return $this->attributes->get($key);
                }
                if ($this->query->has($key)) {
                    return $this->query->get($key);
                }

                return $this->request->get($key, $default);
            }

            public function getContent($asResource = false)
            {
                return (string) $this->content;
            }

            public function getClientIp()
            {
                return $this->server->get('REMOTE_ADDR', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            }

            public function getMethod()
            {
                return $this->method;
            }

            public function setMethod($method)
            {
                $this->method = strtoupper($method);
            }

            public function isMethod($method)
            {
                return $this->method === strtoupper($method);
            }

            public function getSession()
            {
                return $this->session;
            }

            public function setSession($session)
            {
                $this->session = $session;
            }

            public function hasSession()
            {
                return $this->session !== null;
            }
        }
    }

    if (!class_exists(Response::class)) {
        class Response
        {
            /** @var string */
            protected $content;

            /** @var int */
            protected $statusCode;

            /** @var array<string, mixed> */
            public $headers;

            public function __construct($content = '', $status = 200, array $headers = [])
            {
                $this->content = (string) $content;
                $this->statusCode = (int) $status;
                $this->headers = $headers;
            }

            public function getContent()
            {
                return $this->content;
            }

            public function setContent($content)
            {
                $this->content = (string) $content;

                return $this;
            }

            public function getStatusCode()
            {
                return $this->statusCode;
            }

            public function setStatusCode($code)
            {
                $this->statusCode = (int) $code;

                return $this;
            }
        }
    }

    if (!class_exists(JsonResponse::class)) {
        class JsonResponse extends Response
        {
            /** @var mixed the original data, handy for assertions */
            public $data;

            public function __construct($data = null, $status = 200, array $headers = [], $json = false)
            {
                $this->data = $json ? json_decode((string) $data, true) : $data;
                parent::__construct($json ? (string) $data : (string) json_encode($data), $status, $headers);
            }

            public function setData($data = [])
            {
                $this->data = $data;
                $this->content = (string) json_encode($data);

                return $this;
            }
        }
    }

    if (!class_exists(RedirectResponse::class)) {
        class RedirectResponse extends Response
        {
            /** @var string */
            public $targetUrl;

            public function __construct($url, $status = 302, array $headers = [])
            {
                parent::__construct('', $status, $headers);
                $this->targetUrl = (string) $url;
            }

            public function getTargetUrl()
            {
                return $this->targetUrl;
            }
        }
    }

    if (!class_exists(RequestStack::class)) {
        class RequestStack
        {
            /** @var array<int, Request> */
            private $requests = [];

            public function push(Request $request)
            {
                $this->requests[] = $request;
            }

            public function getCurrentRequest()
            {
                return end($this->requests) ?: null;
            }

            public function getMainRequest()
            {
                return $this->requests[0] ?? null;
            }

            public function getMasterRequest()
            {
                return $this->getMainRequest();
            }

            public function getSession()
            {
                $request = $this->getCurrentRequest();

                return $request ? $request->getSession() : null;
            }
        }
    }
}

namespace Symfony\Component\HttpFoundation\Session {
    if (!interface_exists(SessionInterface::class)) {
        interface SessionInterface
        {
            public function get($name, $default = null);

            public function set($name, $value);

            public function has($name);

            public function remove($name);
        }
    }

    if (!class_exists(Session::class)) {
        /** In-memory session for tests. */
        class Session implements SessionInterface
        {
            /** @var array<string, mixed> */
            public $values = [];

            public function get($name, $default = null)
            {
                return array_key_exists($name, $this->values) ? $this->values[$name] : $default;
            }

            public function set($name, $value)
            {
                $this->values[$name] = $value;
            }

            public function has($name)
            {
                return array_key_exists($name, $this->values);
            }

            public function remove($name)
            {
                $value = $this->get($name);
                unset($this->values[$name]);

                return $value;
            }
        }
    }
}

namespace Symfony\Component\DependencyInjection {
    if (!interface_exists(ContainerInterface::class)) {
        interface ContainerInterface
        {
            public function get($id, $invalidBehavior = 1);

            public function has($id);
        }
    }
}

namespace Symfony\Component\HttpKernel {
    if (!interface_exists(KernelInterface::class)) {
        interface KernelInterface
        {
            public function getContainer();
        }
    }
}

namespace Symfony\Component\Security\Csrf {
    if (!class_exists(CsrfToken::class)) {
        class CsrfToken
        {
            /** @var string */
            private $id;

            /** @var string */
            private $value;

            public function __construct($id, $value)
            {
                $this->id = (string) $id;
                $this->value = (string) $value;
            }

            public function getId()
            {
                return $this->id;
            }

            public function getValue()
            {
                return $this->value;
            }

            public function __toString()
            {
                return $this->value;
            }
        }
    }

    if (!interface_exists(CsrfTokenManagerInterface::class)) {
        interface CsrfTokenManagerInterface
        {
            public function getToken($tokenId);

            public function refreshToken($tokenId);

            public function removeToken($tokenId);

            public function isTokenValid(CsrfToken $token);
        }
    }
}

namespace Symfony\Component\Form {
    if (!interface_exists(FormBuilderInterface::class)) {
        interface FormBuilderInterface
        {
            public function add($child, $type = null, array $options = []);
        }
    }

    if (!class_exists(AbstractType::class)) {
        abstract class AbstractType
        {
            public function buildForm(FormBuilderInterface $builder, array $options)
            {
            }

            public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver)
            {
            }
        }
    }
}

namespace Symfony\Component\Form\Extension\Core\Type {
    if (!class_exists(TextType::class)) {
        class TextType extends \Symfony\Component\Form\AbstractType
        {
        }
    }

    if (!class_exists(CheckboxType::class)) {
        class CheckboxType extends \Symfony\Component\Form\AbstractType
        {
        }
    }
}

namespace Symfony\Bundle\FrameworkBundle\Controller {
    if (!class_exists(AbstractController::class)) {
        abstract class AbstractController
        {
            /** @var \Symfony\Component\DependencyInjection\ContainerInterface|null */
            protected $container;

            /** @var array<int, array{0: string, 1: string}> */
            public $flashes = [];

            public function setContainer($container)
            {
                $previous = $this->container;
                $this->container = $container;

                return $previous;
            }

            public static function getSubscribedServices(): array
            {
                return ['router' => '?RouterInterface', 'request_stack' => '?RequestStack'];
            }

            protected function addFlash(string $type, $message): void
            {
                $this->flashes[] = [$type, $message];
            }

            protected function json($data, int $status = 200, array $headers = [], array $context = [])
            {
                return new \Symfony\Component\HttpFoundation\JsonResponse($data, $status, $headers);
            }

            protected function redirectToRoute(string $route, array $parameters = [], int $status = 302)
            {
                return new \Symfony\Component\HttpFoundation\RedirectResponse($route . ($parameters ? '?' . http_build_query($parameters) : ''), $status);
            }

            protected function redirect(string $url, int $status = 302)
            {
                return new \Symfony\Component\HttpFoundation\RedirectResponse($url, $status);
            }

            protected function render(string $view, array $parameters = [], $response = null)
            {
                return new \Symfony\Component\HttpFoundation\Response('render:' . $view . ':' . json_encode(array_keys($parameters)));
            }

            protected function get(string $id)
            {
                return $this->container->get($id);
            }

            protected function has(string $id)
            {
                return $this->container !== null && $this->container->has($id);
            }
        }
    }
}

namespace Doctrine\ORM {
    if (!interface_exists(EntityManagerInterface::class)) {
        interface EntityManagerInterface
        {
            public function getRepository($className);

            public function persist($object);

            public function remove($object);

            public function flush();
        }
    }

    if (!class_exists(EntityManager::class)) {
        /**
         * In-memory entity manager: persist()/remove() are recorded and flush()
         * counts calls. getRepository() returns what the test registered.
         */
        class EntityManager implements EntityManagerInterface
        {
            /** @var array<string, object> */
            public $repositories = [];

            /** @var array<int, object> */
            public $persisted = [];

            /** @var array<int, object> */
            public $removed = [];

            /** @var int */
            public $flushes = 0;

            public function getRepository($className)
            {
                return $this->repositories[$className] ?? null;
            }

            public function persist($object)
            {
                $this->persisted[] = $object;
            }

            public function remove($object)
            {
                $this->removed[] = $object;
            }

            public function flush()
            {
                ++$this->flushes;
            }

            public function createQueryBuilder()
            {
                return \BuckarooTest\Stubs::call('EntityManager->createQueryBuilder', []);
            }
        }
    }

    if (!class_exists(EntityRepository::class)) {
        class EntityRepository
        {
            /** @var EntityManagerInterface|null */
            protected $_em;

            /** @var Mapping\ClassMetadata|null */
            protected $_class;

            /** @var string */
            protected $_entityName = '';

            /** @var array<int, object> rows the find* helpers search */
            public $rows = [];

            public function __construct($em = null, $class = null)
            {
                $this->_em = $em;
                $this->_class = $class;
                $this->_entityName = $class && isset($class->name) ? $class->name : '';
            }

            protected function getEntityManager()
            {
                return $this->_em;
            }

            public function find($id)
            {
                foreach ($this->rows as $row) {
                    if (method_exists($row, 'getId') && (int) $row->getId() === (int) $id) {
                        return $row;
                    }
                }

                return null;
            }

            public function findAll()
            {
                return $this->rows;
            }

            public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
            {
                return array_values(array_filter($this->rows, function ($row) use ($criteria) {
                    return self::matches($row, $criteria);
                }));
            }

            public function findOneBy(array $criteria, ?array $orderBy = null)
            {
                return $this->findBy($criteria)[0] ?? null;
            }

            private static function matches($row, array $criteria): bool
            {
                foreach ($criteria as $field => $value) {
                    $getter = 'get' . str_replace('_', '', ucwords((string) $field, '_'));
                    $actual = method_exists($row, $getter) ? $row->{$getter}() : ($row->{$field} ?? null);
                    if (is_object($actual) && is_object($value)) {
                        if ($actual !== $value) {
                            return false;
                        }
                    } elseif ($actual != $value) {
                        return false;
                    }
                }

                return true;
            }
        }
    }

    if (!class_exists(ORMException::class)) {
        class ORMException extends \Exception
        {
        }
    }

    if (!class_exists(OptimisticLockException::class)) {
        class OptimisticLockException extends ORMException
        {
        }
    }
}

namespace Doctrine\ORM\Exception {
    if (!class_exists(ORMException::class)) {
        class ORMException extends \Exception
        {
        }
    }
}

namespace Doctrine\ORM\Mapping {
    if (!class_exists(ClassMetadata::class)) {
        class ClassMetadata
        {
            /** @var string */
            public $name;

            public function __construct($entityName = '')
            {
                $this->name = $entityName;
            }
        }
    }
}

namespace PrestaShop\PrestaShop\Adapter {
    if (!class_exists(SymfonyContainer::class)) {
        class SymfonyContainer
        {
            /** @var object|null */
            public static $instance;

            public static function getInstance()
            {
                return self::$instance;
            }
        }
    }
}

namespace PrestaShop\PrestaShop\Adapter\Order\Refund {
    if (!class_exists(OrderRefundSummary::class)) {
        class OrderRefundSummary
        {
            use \BuckarooTest\Configurable;
        }
    }

    if (!class_exists(OrderRefundCalculator::class)) {
        class OrderRefundCalculator
        {
            use \BuckarooTest\Configurable;
        }
    }
}

namespace PrestaShop\PrestaShop\Core\Localization\Exception {
    if (!class_exists(LocalizationException::class)) {
        class LocalizationException extends \Exception
        {
        }
    }
}

namespace PrestaShop\PrestaShop\Core\CommandBus {
    if (!interface_exists(CommandBusInterface::class)) {
        interface CommandBusInterface
        {
            public function handle($command);
        }
    }
}

namespace PrestaShop\PrestaShop\Core\Domain\Order {
    if (!class_exists(VoucherRefundType::class)) {
        class VoucherRefundType
        {
            public const PRODUCT_PRICES_REFUND = 0;
            public const PRODUCT_PRICES_EXCLUDING_VOUCHER_REFUND = 1;
            public const SPECIFIC_AMOUNT_REFUND = 2;
        }
    }
}

namespace PrestaShop\PrestaShop\Core\Domain\Order\Exception {
    if (!class_exists(OrderException::class)) {
        class OrderException extends \Exception
        {
        }
    }

    if (!class_exists(InvalidCancelProductException::class)) {
        class InvalidCancelProductException extends OrderException
        {
            public const INVALID_QUANTITY = 1;
            public const QUANTITY_TOO_HIGH = 2;
            public const NO_REFUNDS = 6;
        }
    }
}

namespace PrestaShop\PrestaShop\Core\Domain\Order\Command {
    if (!class_exists(IssuePartialRefundCommand::class)) {
        /** Stores the constructor arguments; the getters mirror the core command. */
        class IssuePartialRefundCommand
        {
            /** @var array<int, mixed> */
            public $args;

            public function __construct(...$args)
            {
                $this->args = $args;
            }

            public function getOrderId()
            {
                return $this->args[0];
            }

            public function getOrderDetailRefunds()
            {
                return $this->args[1] ?? [];
            }

            public function getShippingCostRefundAmount()
            {
                return $this->args[2] ?? null;
            }

            public function restockRefundedProducts()
            {
                return $this->args[3] ?? false;
            }

            public function generateCreditSlip()
            {
                return $this->args[4] ?? false;
            }

            public function generateVoucher()
            {
                return $this->args[5] ?? false;
            }

            public function getVoucherRefundType()
            {
                return $this->args[6] ?? 0;
            }
        }
    }

    if (!class_exists(IssueStandardRefundCommand::class)) {
        class IssueStandardRefundCommand extends IssuePartialRefundCommand
        {
            public function getShippingCostRefundAmount()
            {
                return null;
            }

            public function refundShippingCost()
            {
                return $this->args[2] ?? false;
            }
        }
    }
}

namespace PrestaShop\PrestaShop\Core\Domain\Order\CommandHandler {
    if (!interface_exists(IssuePartialRefundHandlerInterface::class)) {
        interface IssuePartialRefundHandlerInterface
        {
            public function handle(\PrestaShop\PrestaShop\Core\Domain\Order\Command\IssuePartialRefundCommand $command): void;
        }
    }

    if (!interface_exists(IssueStandardRefundHandlerInterface::class)) {
        interface IssueStandardRefundHandlerInterface
        {
            public function handle(\PrestaShop\PrestaShop\Core\Domain\Order\Command\IssueStandardRefundCommand $command): void;
        }
    }
}

namespace PrestaShopBundle\Form {
    if (!class_exists(FormBuilderModifier::class)) {
        class FormBuilderModifier
        {
            use \BuckarooTest\Configurable;
        }
    }
}

namespace PrestaShopBundle\Form\Admin\Type {
    if (!class_exists(TranslatorAwareType::class)) {
        abstract class TranslatorAwareType extends \Symfony\Component\Form\AbstractType
        {
            /** @var mixed */
            protected $translator;

            /** @var array */
            protected $locales;

            public function __construct($translator = null, array $locales = [])
            {
                $this->translator = $translator;
                $this->locales = $locales;
            }

            protected function trans($key, $domain = 'Admin.Global', $parameters = [])
            {
                return strtr($key, $parameters);
            }
        }
    }
}

namespace PrestaShopBundle\Service\Routing {
    if (!class_exists(Router::class)) {
        class Router
        {
            use \BuckarooTest\Configurable;

            public function generate($name, $parameters = [], $referenceType = 1)
            {
                return '/admin/' . $name . ($parameters ? '?' . http_build_query($parameters) : '');
            }
        }
    }
}
