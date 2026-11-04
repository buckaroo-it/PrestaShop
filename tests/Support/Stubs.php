<?php
/**
 * Test-only registry behind the PrestaShop core stubs.
 *
 * Every stub method that is not implemented explicitly goes through this
 * registry: it records the call and returns whatever the test configured
 * (a plain value, or a callable that receives the call arguments).
 *
 * Typical use in a test:
 *
 *     Stubs::returns('Order::getIdByCartId', 42);           // static method
 *     Stubs::object('Order', 42, ['reference' => 'ABC']);   // new Order(42) loads these props
 *     $cart->stub('getProducts', [['id_product' => 1]]);    // one instance only
 *     Stubs::db('/FROM `ps_bk_giftcards`/', [['code' => 'x']]);
 *     self::assertSame(1, Stubs::callCount('PrestaShopLogger::addLog'));
 */

namespace BuckarooTest;

final class Stubs
{
    /** @var array<string, mixed> 'Class::method' or 'Class->method' => value or callable */
    public static $returns = [];

    /** @var array<int, array{0: string, 1: array}> recorded calls in order */
    public static $calls = [];

    /** @var array<string, array<int, array<string, mixed>>> class => id => props for new Class($id) */
    public static $objects = [];

    /** @var array<int, array{op: string, class: string, object: object}> saved/added/updated/deleted objects */
    public static $persisted = [];

    /** @var array<int, array{0: string, 1: mixed}> Db responders: [regex, value|callable] */
    public static $dbResponders = [];

    /** @var array<int, array{method: string, sql: mixed, args: array}> executed Db calls */
    public static $queries = [];

    /** @var int next id handed out by ObjectModel::add() and Db::Insert_ID() */
    public static $nextId = 1000;

    /**
     * Clear every registry and the PrestaShop globals the stubs keep.
     * BuckarooTest\TestCase calls this before each test.
     */
    public static function reset(): void
    {
        self::$returns = [];
        self::$calls = [];
        self::$objects = [];
        self::$persisted = [];
        self::$dbResponders = [];
        self::$queries = [];
        self::$nextId = 1000;

        \Configuration::$values = [];
        \Configuration::$globalValues = [];
        \Country::$isoById = [];
        \Country::$countries = [];
        \Country::$lastLanguageId = null;
        \Currency::$isoById = [];
        \Module::$instances = [];
        \Context::reset();
        \SendcloudServicePoint::$details = [];

        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
    }

    /**
     * Configure what a stubbed method returns.
     *
     * @param string $signature 'Class::method' for static calls, 'Class->method' for any instance
     * @param mixed $value a value, or a callable receiving the call arguments
     */
    public static function returns(string $signature, $value): void
    {
        self::$returns[$signature] = $value;
    }

    /** Make a stubbed method throw. */
    public static function throws(string $signature, \Throwable $error): void
    {
        self::$returns[$signature] = static function () use ($error) {
            throw $error;
        };
    }

    /** Register the properties new Class($id) loads. */
    public static function object(string $class, int $id, array $props = []): void
    {
        self::$objects[ltrim($class, '\\')][$id] = $props;
    }

    /**
     * Register a Db responder. The first responder whose regex matches the SQL wins.
     *
     * @param string $pattern regex matched against the SQL (DbQuery is cast to string)
     * @param mixed $result value, or callable(string $sql, string $method, array $args)
     */
    public static function db(string $pattern, $result): void
    {
        self::$dbResponders[] = [$pattern, $result];
    }

    /** Record a call and resolve its configured return value. */
    public static function call(string $signature, array $args, $default = null)
    {
        self::$calls[] = [$signature, $args];

        if (!array_key_exists($signature, self::$returns)) {
            return $default;
        }

        $value = self::$returns[$signature];

        return is_callable($value) && !is_string($value) ? $value(...$args) : $value;
    }

    /** @return array<int, array> argument lists of every recorded call to $signature */
    public static function callsTo(string $signature): array
    {
        $found = [];
        foreach (self::$calls as [$sig, $args]) {
            if ($sig === $signature) {
                $found[] = $args;
            }
        }

        return $found;
    }

    public static function callCount(string $signature): int
    {
        return count(self::callsTo($signature));
    }

    /** @return array<int, object> objects of $class persisted with $op ('add', 'update', 'save', 'delete'), or any op */
    public static function persisted(string $class, ?string $op = null): array
    {
        $found = [];
        foreach (self::$persisted as $entry) {
            if ($entry['class'] === ltrim($class, '\\') && ($op === null || $entry['op'] === $op)) {
                $found[] = $entry['object'];
            }
        }

        return $found;
    }

    /** @return array<int, string> SQL of every recorded Db call (optionally only one method) */
    public static function sql(?string $method = null): array
    {
        $found = [];
        foreach (self::$queries as $query) {
            if ($method === null || $query['method'] === $method) {
                $found[] = (string) $query['sql'];
            }
        }

        return $found;
    }

    /** Resolve a Db call against the registered responders. */
    public static function dbResult(string $method, $sql, array $args, $default)
    {
        self::$queries[] = ['method' => $method, 'sql' => $sql, 'args' => $args];
        $text = is_string($sql) || (is_object($sql) && method_exists($sql, '__toString')) ? (string) $sql : json_encode($sql);

        foreach (self::$dbResponders as [$pattern, $result]) {
            if (preg_match($pattern, $text)) {
                return is_callable($result) && !is_string($result) ? $result($text, $method, $args) : $result;
            }
        }

        return $default;
    }
}

/**
 * Lets a stub answer methods it does not implement: each call is recorded and
 * returns what the test configured, per instance ($obj->stub()) or per class
 * (Stubs::returns('Class->method')).
 */
trait Configurable
{
    /** @var array<string, mixed> */
    public $stubbed = [];

    /** Configure one method on this instance only. */
    public function stub(string $method, $value): self
    {
        $this->stubbed[$method] = $value;

        return $this;
    }

    public function __call($method, $args)
    {
        $class = get_class($this);
        if (array_key_exists($method, $this->stubbed)) {
            Stubs::$calls[] = [$class . '->' . $method, $args];
            $value = $this->stubbed[$method];

            return is_callable($value) && !is_string($value) ? $value(...$args) : $value;
        }

        // Resolve on the class and its parents ('Cart->getProducts' also answers a Cart subclass).
        for ($c = $class; $c !== false; $c = get_parent_class($c)) {
            if (array_key_exists($c . '->' . $method, Stubs::$returns)) {
                return Stubs::call($c . '->' . $method, $args);
            }
        }

        return Stubs::call($class . '->' . $method, $args);
    }

    public static function __callStatic($method, $args)
    {
        for ($c = static::class; $c !== false; $c = get_parent_class($c)) {
            if (array_key_exists($c . '::' . $method, Stubs::$returns)) {
                return Stubs::call($c . '::' . $method, $args);
            }
        }

        return Stubs::call(static::class . '::' . $method, $args);
    }
}

/** Thrown by Tools::redirect() and friends so tests can assert on the target instead of exiting. */
class RedirectException extends \RuntimeException
{
    /** @var string */
    public $url;

    public function __construct(string $url)
    {
        parent::__construct('Redirect to ' . $url);
        $this->url = $url;
    }
}

/** Thrown by stubbed ajaxDie()/terminate helpers that end the request in PrestaShop. */
class TerminateException extends \RuntimeException
{
    /** @var mixed */
    public $output;

    public function __construct($output = null)
    {
        parent::__construct('Request terminated');
        $this->output = $output;
    }
}
