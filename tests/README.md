# Unit tests

The suite runs the module code against lightweight PrestaShop, Symfony and
Doctrine stubs, so no shop or database is needed.

```bash
composer test                                   # all tests, no coverage
php -d pcov.directory=. vendor/bin/phpunit --coverage-text   # with coverage (PCOV)
vendor/bin/phpunit tests/Unit/Controller/Front  # one folder
```

Coverage of individual files (Clover report + helper):

```bash
php -d pcov.directory=. vendor/bin/phpunit --coverage-clover /tmp/clover.xml
php tests/tools/coverage-report.php /tmp/clover.xml controllers/front --uncovered
php tests/tools/coverage-report.php /tmp/clover.xml --min=90   # what CI enforces
```

PCOV only records `src/` by default when that folder exists, so always pass
`-d pcov.directory=.` (CI does the same); otherwise `api/`, `controllers/`,
`library/`, `upgrade/` and `buckaroo3.php` show 0%.

## How the stubs work

- `tests/bootstrap.php` defines the PrestaShop constants and loads
  `tests/Support/*`. `_PS_MODULE_DIR_` points to a temporary folder holding a
  `buckaroo3` symlink to this repository, so the module's own
  `include_once _PS_MODULE_DIR_ . 'buckaroo3/...'` statements load the real
  files. **Never stub a module class** (Checkout, CarrierHandler, ...): it
  would collide with the real one.
- `tests/Support/PrestaShopStubs.php`: core classes (`Cart`, `Order`,
  `Customer`, `Address`, `Tools`, `Configuration`, `Db`, `Context`, `Module`,
  `PaymentModule`, `ModuleFrontController`, `ModuleAdminController`, ...).
- `tests/Support/FrameworkStubs.php`: Symfony HttpFoundation, DI, CSRF and
  Form types, Doctrine ORM, PrestaShop core commands and exceptions.
- `tests/Support/Stubs.php`: the registry behind them (`BuckarooTest\Stubs`).

Extend `BuckarooTest\TestCase`: it resets every stub and the request globals
before each test and adds helpers.

### Recipes

```php
use BuckarooTest\Stubs;

// Request parameters (Tools::getValue reads POST, then GET)
$this->request(['id_cart' => '5'], ['amount' => '10']);

// Configuration values
\Configuration::updateValue('BUCKAROO_TEST', 1);

// new Order(42) loads these properties; an unregistered id leaves $order->id null
Stubs::object('Order', 42, ['reference' => 'ABC', 'id_cart' => 5, 'total_paid' => 10.0]);

// Static methods without an explicit stub implementation
Stubs::returns('Order::getIdByCartId', 42);
Stubs::returns('Order::getIdByCartId', function ($cartId) { return $cartId === 5 ? 42 : 0; });
Stubs::throws('Mail::Send', new \RuntimeException('smtp down'));

// Instance methods: one object, or every instance of a class
$cart->stub('getProducts', [['id_product' => 1, 'quantity' => 2]]);
Stubs::returns('Order->getOrderPayments', []);

// Database: the first responder whose regex matches the SQL wins
Stubs::db('/FROM `ps_bk_giftcards`/', [['code' => 'fashioncheque']]);
Stubs::db('/UPDATE `ps_orders`/', false);       // make a write fail
self::assertStringContainsString('ps_bk_creditcards', Stubs::sql('execute')[0]);

// Assertions on calls and persisted objects
self::assertSame(1, Stubs::callCount('PrestaShopLogger::addLog'));
self::assertSame('Refund failed', Stubs::callsTo('PrestaShopLogger::addLog')[0][0]);
self::assertCount(1, Stubs::persisted('OrderHistory', 'add'));

// Context (fresh each test): cart, customer, cookie, link, smarty, shop, language, currency
$this->context()->cart = $cart;
self::assertSame('x', $this->context()->smarty->getTemplateVars('foo'));

// Redirects and terminated requests never exit
$url = $this->expectRedirect(function () use ($controller) { $controller->postProcess(); }, 'order-confirmation');
$payload = $this->expectTerminate(function () use ($controller) { $controller->displayAjax(); });
```

Front controllers read `Module::getInstanceByName('buckaroo3')` in their
constructor; register a module double with `\Module::$instances['buckaroo3'] = $module;`
first. Admin API controllers (`BaseApiController` subclasses) take a
`Symfony\Component\HttpFoundation\Request` and return a `JsonResponse` whose
`->data` holds the decoded payload.

Never let a test reach the network: the payment classes create a real
`BuckarooClient` in `PaymentMethod::getBuckarooClient()`; override it in a
test subclass and return a double.

## Rules for new tests

- Assert behaviour (returned values, redirects, persisted data, SQL, logged
  messages), not just that a line ran.
- Tests must pass on PHP 7.4 through 8.5: no PHP 8-only syntax in test code
  (named arguments, `match`, nullsafe `?->`, constructor promotion, union
  types, `mixed`, enums, `readonly`).
- Keep each test independent: rely on the reset in `BuckarooTest\TestCase`
  instead of state left by another test.
