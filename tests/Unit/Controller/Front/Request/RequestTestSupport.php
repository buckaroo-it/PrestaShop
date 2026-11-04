<?php
/**
 * Test doubles for the front controllers of the payment start flow
 * (request.php, common.php, error.php).
 *
 * No PrestaShop or module class is stubbed here: the doubles extend the real
 * classes (Link, BuckarooPaymentService) or stand in for objects the
 * controllers only call methods on (checkout, payment response). Doubles of
 * module classes live in ModuleDoubles.php, loaded by loadControllers().
 */

namespace BuckarooTest\Unit\Controller\Front\Request;

use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentService;
use BuckarooTest\RedirectException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Load the controllers and the doubles that extend module classes. Tests call
 * this from setUp() rather than at file load, so the files' top-level
 * statements run while coverage is recorded.
 */
function loadControllers(): void
{
    require_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/request.php';
    require_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/error.php';
    require_once _PS_MODULE_DIR_ . 'buckaroo3/api/abstract.php';
    require_once _PS_MODULE_DIR_ . 'buckaroo3/buckaroo3.php';
    require_once __DIR__ . '/ModuleDoubles.php';
}

if (!class_exists(ConfigServiceDouble::class)) {
    class ConfigServiceDouble
    {
        /** @var array<string, array> configuration array per payment method */
        public $configs = [];

        public function getConfigArrayForMethod($method)
        {
            return $this->configs[$method] ?? [];
        }

        public function getConfigValue($method, $key)
        {
            return $this->configs[$method][$key] ?? null;
        }
    }
}

if (!class_exists(PaymentServiceDouble::class)) {
    /** The payment service checks the controller relies on, answered from public fields. */
    class PaymentServiceDouble extends BuckarooPaymentService
    {
        public $methodAvailable = true;
        public $brandAllowed = true;
        public $issuerAllowed = true;

        /** @var array<int, array{0: string, 1: array}> */
        public $calls = [];

        public function __construct()
        {
        }

        public function isMethodAvailableForCart($cart, string $method): bool
        {
            $this->calls[] = ['isMethodAvailableForCart', [$cart, $method]];

            return $this->methodAvailable;
        }

        public function isCreditCardBrandAllowed(string $brand): bool
        {
            $this->calls[] = ['isCreditCardBrandAllowed', [$brand]];

            return $this->brandAllowed;
        }

        public function isPayByBankIssuerAllowed(string $issuer): bool
        {
            $this->calls[] = ['isPayByBankIssuerAllowed', [$issuer]];

            return $this->issuerAllowed;
        }
    }
}

if (!class_exists(ContainerDouble::class)) {
    class ContainerDouble implements ContainerInterface
    {
        /** @var array<string, mixed> */
        public $services;

        public function __construct(array $services = [])
        {
            $this->services = $services;
        }

        public function get($id, $invalidBehavior = 1)
        {
            if (!array_key_exists($id, $this->services)) {
                throw new \RuntimeException('Service ' . $id . ' not found');
            }

            return $this->services[$id];
        }

        public function has($id)
        {
            return array_key_exists($id, $this->services);
        }
    }
}

if (!class_exists(HookLink::class)) {
    /**
     * Link that runs a callback the first time a module link is built. The
     * request controller builds its return URLs right after Checkout::getInstance(),
     * which is where the tests swap the real checkout for a FakeCheckout.
     */
    class HookLink extends \Link
    {
        /** @var callable|null */
        public $onModuleLink;

        public function __construct(?callable $onModuleLink = null)
        {
            $this->onModuleLink = $onModuleLink;
        }

        public function getModuleLink($module, $controller = 'default', array $params = [], $ssl = null, $idLang = null, $idShop = null, $relativeProtocol = false)
        {
            if ($this->onModuleLink !== null) {
                $callback = $this->onModuleLink;
                $this->onModuleLink = null;
                $callback();
            }

            return parent::getModuleLink($module, $controller, $params, $ssl, $idLang, $idShop, $relativeProtocol);
        }
    }
}

if (!class_exists(FakeCheckout::class)) {
    /** Stands in for a Checkout subclass; records what the controller does with it. */
    class FakeCheckout
    {
        public $platformName;
        public $platformVersion;
        public $moduleSupplier;
        public $moduleName;
        public $moduleVersion;
        public $returnUrl;
        public $pushUrl;
        public $reference;

        public $verifyRequired = false;
        public $requestSucceeded = true;
        public $redirectRequired = false;
        public $redirectUrl = 'https://checkout.buckaroo.test/pay';

        /** @var object|null */
        public $response;

        /** @var \Exception|null thrown by setCheckout() */
        public $setCheckoutError;

        /** @var \Exception|null thrown by startPayment() */
        public $startPaymentError;

        /** @var string[] */
        public $calls = [];

        /** @var array|null */
        public $verifyArgs;

        /** Take over what the controller already set on the real checkout. */
        public function adopt($checkout): self
        {
            foreach (['platformName', 'platformVersion', 'moduleSupplier', 'moduleName', 'moduleVersion', 'returnUrl', 'pushUrl'] as $property) {
                $this->{$property} = $checkout->{$property};
            }

            return $this;
        }

        public function isVerifyRequired()
        {
            return $this->verifyRequired;
        }

        public function setReference($reference)
        {
            $this->reference = $reference;
        }

        public function setCheckout()
        {
            $this->calls[] = 'setCheckout';
            if ($this->setCheckoutError) {
                throw $this->setCheckoutError;
            }
        }

        public function startVerify($customVars = [])
        {
            $this->calls[] = 'startVerify';
            $this->verifyArgs = $customVars;
        }

        public function startPayment()
        {
            $this->calls[] = 'startPayment';
            if ($this->startPaymentError) {
                throw $this->startPaymentError;
            }
        }

        public function isRequestSucceeded()
        {
            return $this->requestSucceeded;
        }

        public function getResponse()
        {
            return $this->response;
        }

        public function isRedirectRequired()
        {
            return $this->redirectRequired;
        }

        public function doRedirect($redirectUrl = null)
        {
            $this->calls[] = 'doRedirect';
            \Tools::redirect($redirectUrl ?: $this->redirectUrl);
        }
    }
}

if (!class_exists(FakeResponse::class)) {
    /** The parts of the module Response the request controller reads. */
    class FakeResponse
    {
        public $payment_method = 'ideal';
        public $consumerMessage = [];
        public $statusmessage = '';
        public $status = 0;
        public $transactions;
        public $amount;
        public $brq_transaction_type;

        public $succeeded = true;
        public $valid = true;
        public $paid = false;
        public $cartId = 0;
        public $someError = '';

        /** @var object|null SDK TransactionResponse */
        public $transactionResponse;

        public function hasSucceeded()
        {
            return $this->succeeded;
        }

        public function isValid()
        {
            return $this->valid;
        }

        public function isPaid()
        {
            return $this->paid;
        }

        public function getCartId()
        {
            return $this->cartId;
        }

        public function getResponse()
        {
            return $this->transactionResponse;
        }

        public function getSomeError()
        {
            return $this->someError;
        }
    }
}

if (!function_exists(__NAMESPACE__ . '\redirectTargets')) {
    /**
     * Run $callback and return every Tools::redirect target, also when the
     * controller caught the RedirectException itself.
     *
     * @return string[]
     */
    function redirectTargets(callable $callback): array
    {
        try {
            $callback();
        } catch (RedirectException $redirect) {
            // collected below
        }

        $targets = [];
        foreach (\BuckarooTest\Stubs::callsTo('Tools::redirect') as $args) {
            $targets[] = (string) $args[0];
        }

        return $targets;
    }
}
