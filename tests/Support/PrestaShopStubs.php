<?php
/**
 * Lightweight PrestaShop core stubs for unit tests.
 *
 * They exist so the module code can be loaded and run without a shop. Each
 * class implements the behaviour the module relies on; anything else answers
 * through BuckarooTest\Configurable (recorded, returns what the test set up).
 * See tests/Support/Stubs.php for the registry API.
 */

use BuckarooTest\Configurable;
use BuckarooTest\RedirectException;
use BuckarooTest\Stubs;

// ---------------------------------------------------------------------------
// Objects loaded by id
// ---------------------------------------------------------------------------

if (!class_exists('ObjectModel')) {
    #[AllowDynamicProperties]
    abstract class ObjectModel
    {
        use Configurable;

        public $id;

        /** @var string table name some module code reads */
        public static $definition = ['table' => '', 'primary' => 'id'];

        /**
         * new Class($id) loads the props registered with Stubs::object(); an unknown
         * id leaves $id null, like PrestaShop does for a missing row.
         */
        public function __construct($id = null, $idLang = null, $idShop = null)
        {
            $class = get_class($this);
            for ($c = $class; $c !== false; $c = get_parent_class($c)) {
                if ($id !== null && isset(Stubs::$objects[$c][(int) $id])) {
                    $this->hydrate(Stubs::$objects[$c][(int) $id]);
                    $this->id = (int) $id;
                    break;
                }
            }
        }

        public function hydrate(array $props): self
        {
            foreach ($props as $key => $value) {
                $this->{$key} = $value;
            }

            return $this;
        }

        public function add($autoDate = true, $nullValues = false)
        {
            return $this->persist('add');
        }

        public function update($nullValues = false)
        {
            return $this->persist('update');
        }

        public function save($nullValues = false, $autoDate = true)
        {
            return $this->persist('save');
        }

        public function delete()
        {
            return $this->persist('delete');
        }

        protected function persist(string $op)
        {
            $class = get_class($this);
            $sig = $class . '->' . $op;
            if (array_key_exists($sig, Stubs::$returns) || array_key_exists($op, $this->stubbed)) {
                $result = array_key_exists($op, $this->stubbed)
                    ? (is_callable($this->stubbed[$op]) ? ($this->stubbed[$op])($this) : $this->stubbed[$op])
                    : Stubs::call($sig, [$this]);
                if (!$result) {
                    return $result;
                }
            } else {
                Stubs::$calls[] = [$sig, [$this]];
            }

            if (in_array($op, ['add', 'save'], true) && empty($this->id)) {
                $this->id = Stubs::$nextId++;
            }
            Stubs::$persisted[] = ['op' => $op, 'class' => $class, 'object' => $this];
            if ($op !== 'delete') {
                Stubs::$objects[$class][(int) $this->id] = get_object_vars($this);
            }

            return true;
        }

        public static function existsInDatabase($id, $table)
        {
            return Stubs::call(static::class . '::existsInDatabase', [$id, $table], false);
        }
    }
}

if (!class_exists('Cart')) {
    class Cart extends ObjectModel
    {
        public const ONLY_PRODUCTS = 1;
        public const ONLY_DISCOUNTS = 2;
        public const BOTH = 3;
        public const BOTH_WITHOUT_SHIPPING = 4;
        public const ONLY_SHIPPING = 5;
        public const ONLY_WRAPPING = 6;
        public const ONLY_PRODUCTS_WITHOUT_SHIPPING = 7;
        public const ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING = 8;

        public $id_customer = 0;
        public $id_address_delivery = 0;
        public $id_address_invoice = 0;
        public $id_currency = 1;
        public $id_lang = 1;
        public $id_carrier = 0;
        public $id_shop = 1;
        public $id_shop_group = 1;
        public $secure_key = 'secure';

        /** @var array<int, float> totals by Cart::* type, used by getOrderTotal() */
        public $totals = [];

        /** @var array<int, array<string, mixed>> */
        public $products = [];

        public function getOrderTotal($withTaxes = true, $type = self::BOTH, $products = null, $idCarrier = null, $useCache = false, bool $keepOrderPrices = false)
        {
            if (array_key_exists('getOrderTotal', $this->stubbed) || array_key_exists('Cart->getOrderTotal', Stubs::$returns)) {
                return $this->__call('getOrderTotal', func_get_args());
            }
            $key = (int) $type . ($withTaxes ? '' : '_excl');
            if (isset($this->totals[$key])) {
                return $this->totals[$key];
            }

            return $this->totals[(int) $type] ?? 0.0;
        }

        public function getProducts($refresh = false, $idProduct = false, $idCountry = null, $fullInfos = true, bool $keepOrderPrices = false)
        {
            if (array_key_exists('getProducts', $this->stubbed)) {
                return $this->__call('getProducts', func_get_args());
            }

            return $this->products;
        }

        public function nbProducts()
        {
            if (array_key_exists('nbProducts', $this->stubbed)) {
                return $this->__call('nbProducts', []);
            }

            return count($this->products);
        }
    }
}

if (!class_exists('Order')) {
    class Order extends ObjectModel
    {
        public $id_cart;
        public $id_currency = 1;
        public $id_customer;
        public $id_address_invoice;
        public $id_address_delivery;
        public $id_lang = 1;
        public $id_shop = 1;
        public $reference;
        public $module = 'buckaroo3';
        public $payment;
        public $total_paid = 0.0;
        public $total_paid_tax_incl = 0.0;
        public $total_paid_tax_excl = 0.0;
        public $total_shipping = 0.0;
        public $total_shipping_tax_incl = 0.0;
        public $total_shipping_tax_excl = 0.0;
        public $total_wrapping = 0.0;
        public $total_discounts = 0.0;
        public $carrier_tax_rate = 0.0;
        public $current_state = 0;
        public $secure_key = 'secure';
        public $date_add = '2026-01-01 00:00:00';
        public $invoice_number = 0;
        public $conversion_rate = 1.0;

        public function getCurrentState()
        {
            if (array_key_exists('getCurrentState', $this->stubbed)) {
                return $this->__call('getCurrentState', []);
            }

            return $this->current_state;
        }

        public function setCurrentState($idOrderState, $idEmployee = 0)
        {
            Stubs::$calls[] = ['Order->setCurrentState', [$idOrderState, $idEmployee, $this]];
            $this->current_state = (int) $idOrderState;

            return true;
        }
    }
}

if (!class_exists('Customer')) {
    class Customer extends ObjectModel
    {
        public $firstname = 'Jan';
        public $lastname = 'Jansen';
        public $email = 'buyer@example.com';
        public $birthday = '1990-01-01';
        public $id_gender = 1;
        public $is_guest = 0;
        public $secure_key = 'secure';
        public $company = '';
        public $id_lang = 1;
        public $id_default_group = 3;
    }
}

if (!class_exists('Address')) {
    class Address extends ObjectModel
    {
        public $id_customer;
        public $id_country = 0;
        public $id_state = 0;
        public $firstname = 'Jan';
        public $lastname = 'Jansen';
        public $company = '';
        public $address1 = 'Hoofdstraat 1';
        public $address2 = '';
        public $postcode = '1234AB';
        public $city = 'Amsterdam';
        public $phone = '';
        public $phone_mobile = '';
        public $vat_number = '';
        public $dni = '';
        public $alias = 'Home';
    }
}

if (!class_exists('Carrier')) {
    class Carrier extends ObjectModel
    {
        public $name = 'Carrier';
        public $external_module_name = '';
    }
}

if (!class_exists('Product')) {
    class Product extends ObjectModel
    {
        public $name = 'Product';
        public $reference = '';
        public $price = 0.0;
        public $active = 1;
        public $id_tax_rules_group = 0;
        public $link_rewrite = 'product';
    }
}

foreach ([
    'State', 'OrderState', 'OrderHistory', 'OrderPayment', 'OrderSlip', 'OrderDetail',
    'OrderInvoice', 'OrderCarrier', 'Message', 'CustomerMessage', 'CustomerThread', 'Language',
    'Group', 'Combination', 'Tab', 'Employee', 'CartRule', 'Zone', 'Manufacturer',
] as $buckarooStubClass) {
    if (!class_exists($buckarooStubClass)) {
        eval('class ' . $buckarooStubClass . ' extends ObjectModel {}');
    }
}
unset($buckarooStubClass);

if (!class_exists('Shop')) {
    class Shop extends ObjectModel
    {
        public const CONTEXT_SHOP = 1;
        public const CONTEXT_GROUP = 2;
        public const CONTEXT_ALL = 4;

        public $id = 1;
        public $id_shop_group = 1;
        public $name = 'Test shop';
        public $domain = 'shop.test';
        public $domain_ssl = 'shop.test';

        public function getBaseURL($autoSecureMode = false, $addBaseUri = true)
        {
            return 'https://shop.test/';
        }

        public static function isFeatureActive()
        {
            return (bool) Stubs::call('Shop::isFeatureActive', [], false);
        }

        public static function setContext($type, $id = null)
        {
            return Stubs::call('Shop::setContext', [$type, $id], true);
        }
    }
}

if (!class_exists('Currency')) {
    class Currency extends ObjectModel
    {
        public $iso_code = 'EUR';
        public $sign = '€';
        public $conversion_rate = 1.0;
        public $precision = 2;

        /** @var array<int, string> */
        public static $isoById = [];

        public function __construct($id = null, $idLang = null, $idShop = null)
        {
            parent::__construct($id, $idLang, $idShop);
            if ($id !== null && isset(self::$isoById[(int) $id])) {
                $this->iso_code = self::$isoById[(int) $id];
                $this->id = (int) $id;
            } elseif ($id !== null && $this->id === null) {
                $this->id = (int) $id;
            }
        }

        public static function getIsoCodeById($id, bool $forceRefreshCache = false)
        {
            return self::$isoById[(int) $id] ?? 'EUR';
        }

        public static function getIdByIsoCode($isoCode, $idShop = 0, $forceRefreshCache = false, $includeDeleted = false)
        {
            $id = array_search($isoCode, self::$isoById, true);

            return $id === false ? (int) Stubs::call('Currency::getIdByIsoCode', [$isoCode], 0) : (int) $id;
        }
    }
}

if (!class_exists('Country')) {
    class Country extends ObjectModel
    {
        /** @var array<int, string> */
        public static $isoById = [];

        /** @var array<int, array<string, mixed>> rows returned by getCountries() */
        public static $countries = [];

        /** @var int|null language id passed to the last getCountries() call */
        public static $lastLanguageId;

        public $iso_code = 'NL';
        public $name = 'Netherlands';
        public $need_zip_code = true;
        public $zip_code_format = '';

        public function __construct($id = null, $idLang = null, $idShop = null)
        {
            parent::__construct($id, $idLang, $idShop);
            if ($id !== null && isset(self::$isoById[(int) $id])) {
                $this->iso_code = self::$isoById[(int) $id];
                $this->id = (int) $id;
            }
        }

        public static function getIsoById($id)
        {
            return self::$isoById[(int) $id] ?? null;
        }

        public static function getByIso($isoCode, $active = false)
        {
            $id = array_search(strtoupper((string) $isoCode), self::$isoById, true);

            return $id === false ? false : (int) $id;
        }

        public static function getCountries($idLang, $active = false, $containStates = false, $listStates = true)
        {
            self::$lastLanguageId = (int) $idLang;

            return self::$countries;
        }
    }
}

// ---------------------------------------------------------------------------
// Static helpers
// ---------------------------------------------------------------------------

if (!class_exists('Tools')) {
    class Tools
    {
        use Configurable;

        public static function getContextLocale($context)
        {
            return new class {
                public function formatPrice($amount, $isoCode)
                {
                    // Simple, deterministic representation for assertions
                    return sprintf('%s %.2f', $isoCode, $amount);
                }
            };
        }

        public static function strtoupper($string)
        {
            return strtoupper((string) $string);
        }

        public static function strtolower($string)
        {
            return strtolower((string) $string);
        }

        public static function substr($string, $start, $length = null)
        {
            return $length === null ? substr((string) $string, $start) : substr((string) $string, $start, $length);
        }

        public static function strlen($string)
        {
            return strlen((string) $string);
        }

        /** Mirrors PrestaShop: POST first, then GET. */
        public static function getValue($key, $defaultValue = false)
        {
            if (isset($_POST[$key])) {
                return $_POST[$key];
            }

            return $_GET[$key] ?? $defaultValue;
        }

        public static function getIsset($key)
        {
            return isset($_POST[$key]) || isset($_GET[$key]);
        }

        /** Mirrors PrestaShop: POST wins over GET. */
        public static function getAllValues()
        {
            return $_POST + $_GET;
        }

        public static function isSubmit($submit)
        {
            return isset($_POST[$submit]) || isset($_GET[$submit]);
        }

        /** Ends the request in PrestaShop; tests catch BuckarooTest\RedirectException instead. */
        public static function redirect($url, $baseUri = __PS_BASE_URI__, $link = null, $headers = null)
        {
            Stubs::$calls[] = ['Tools::redirect', [$url]];
            throw new RedirectException((string) $url);
        }

        public static function redirectAdmin($url)
        {
            Stubs::$calls[] = ['Tools::redirectAdmin', [$url]];
            throw new RedirectException((string) $url);
        }

        public static function ps_round($value, $precision = 0, $roundMode = null)
        {
            return round((float) $value, (int) $precision);
        }

        public static function getToken($page = true, $context = null)
        {
            return 'token';
        }

        public static function getAdminTokenLite($tab, $context = null)
        {
            return 'admintoken';
        }

        // No displayPrice(): it was removed in PrestaShop 9, which the bootstrap mimics.
        // Tests that need it configure Stubs::returns('Tools::displayPrice', ...).

        public static function convertPrice($price, $currency = null, $toCurrency = true, $context = null)
        {
            return $price;
        }

        public static function getShopDomainSsl($http = false, $entities = false)
        {
            return ($http ? 'https://' : '') . 'shop.test';
        }

        public static function getShopDomain($http = false, $entities = false)
        {
            return ($http ? 'http://' : '') . 'shop.test';
        }

        public static function getRemoteAddr()
        {
            return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }

        public static function passwdGen($length = 8, $flag = 'ALPHANUMERIC')
        {
            return substr(str_repeat('a1', $length), 0, $length);
        }

        public static function file_get_contents($url, $useIncludePath = false, $streamContext = null, $curlTimeout = 5, $fallback = false)
        {
            return Stubs::call('Tools::file_get_contents', [$url], false);
        }

        public static function getCountry($address = null)
        {
            return Stubs::call('Tools::getCountry', [$address], 0);
        }

        public static function jsonEncode($data, $options = 0)
        {
            return json_encode($data, $options);
        }

        public static function jsonDecode($data, $assoc = false)
        {
            return json_decode($data, $assoc);
        }

        public static function safeOutput($string, $html = false)
        {
            return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
        }

        public static function getHttpHost($http = false, $entities = false, $ignorePort = false)
        {
            return ($http ? 'https://' : '') . 'shop.test';
        }
    }
}

if (!class_exists('Configuration')) {
    class Configuration
    {
        use Configurable;

        /** @var array<string, mixed> */
        public static $values = [];

        /** @var array<string, mixed> */
        public static $globalValues = [];

        public static function get($key, $idLang = null, $idShopGroup = null, $idShop = null, $default = false)
        {
            return self::$values[$key] ?? $default;
        }

        public static function getGlobalValue($key, $idLang = null)
        {
            return self::$globalValues[$key] ?? (self::$values[$key] ?? false);
        }

        public static function updateValue($key, $value, $html = false, $idShopGroup = null, $idShop = null)
        {
            self::$values[$key] = $value;

            return true;
        }

        public static function updateGlobalValue($key, $value, $html = false)
        {
            self::$globalValues[$key] = $value;
            self::$values[$key] = $value;

            return true;
        }

        public static function deleteByName($key)
        {
            unset(self::$values[$key], self::$globalValues[$key]);

            return true;
        }

        public static function hasKey($key, $idLang = null, $idShopGroup = null, $idShop = null)
        {
            return array_key_exists($key, self::$values);
        }

        public static function loadConfiguration()
        {
            Stubs::$calls[] = ['Configuration::loadConfiguration', []];
        }
    }
}

if (!class_exists('Validate')) {
    class Validate
    {
        use Configurable;

        /** Like PrestaShop: an object counts as loaded when it has an id. */
        public static function isLoadedObject($object)
        {
            if (!is_object($object)) {
                return false;
            }

            return !property_exists($object, 'id') || !empty($object->id);
        }

        public static function isEmail($email)
        {
            return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
        }

        public static function isName($name)
        {
            return is_string($name) && !preg_match('/[0-9!<>,;?=+()@#"°{}_$%:]/u', $name);
        }

        public static function isPostCode($postcode)
        {
            return is_string($postcode) && (bool) preg_match('/^[a-zA-Z 0-9-]+$/', $postcode);
        }

        public static function isCityName($city)
        {
            return is_string($city) && !preg_match('/[!<>;?=+@#"°{}_$%]/u', $city);
        }

        public static function isAddress($address)
        {
            return is_string($address) && !preg_match('/[!<>?=+@{}_$%]/u', $address);
        }

        public static function isPhoneNumber($number)
        {
            return (bool) preg_match('/^[+0-9. ()\/-]*$/', (string) $number);
        }

        public static function isUnsignedId($id)
        {
            return is_numeric($id) && (int) $id >= 0 && (int) $id < 4294967296;
        }

        public static function isUnsignedInt($value)
        {
            return self::isUnsignedId($value);
        }

        public static function isCleanHtml($html)
        {
            return !preg_match('/<\s*script/i', (string) $html);
        }
    }
}

if (!class_exists('PrestaShopLogger')) {
    class PrestaShopLogger
    {
        use Configurable;

        public static function addLog($message, $severity = 1, $errorCode = null, $objectType = null, $objectId = null, $allowDuplicate = false, $idEmployee = null)
        {
            return Stubs::call('PrestaShopLogger::addLog', func_get_args(), true);
        }
    }
}

foreach (['Media', 'Mail', 'Hook', 'Cache', 'Translate', 'TaxManagerFactory', 'ImageType', 'Search', 'Tax', 'Image'] as $buckarooStubClass) {
    if (!class_exists($buckarooStubClass)) {
        eval('class ' . $buckarooStubClass . ' { use \BuckarooTest\Configurable; }');
    }
}
unset($buckarooStubClass);

if (!class_exists('PrestaShopCollection')) {
    class PrestaShopCollection implements IteratorAggregate, Countable
    {
        use Configurable;

        /** @var array<int, object> */
        public $items = [];

        public function __construct($class = '', $idLang = null)
        {
            $this->items = (array) Stubs::call('PrestaShopCollection::' . $class, [$idLang], []);
        }

        public function where($field, $operator, $value)
        {
            return $this;
        }

        public function getFirst()
        {
            return $this->items[0] ?? false;
        }

        public function getResults()
        {
            return $this->items;
        }

        #[ReturnTypeWillChange]
        public function getIterator()
        {
            return new ArrayIterator($this->items);
        }

        #[ReturnTypeWillChange]
        public function count()
        {
            return count($this->items);
        }
    }
}

// ---------------------------------------------------------------------------
// Third-party module classes the module calls when those modules are installed
// ---------------------------------------------------------------------------

if (!class_exists('CartCore')) {
    // PrestaShop core declares Cart extends CartCore; the module reads CartCore::ONLY_* constants.
    class_alias('Cart', 'CartCore');
}

if (!class_exists('SendcloudServicePoint')) {
    /**
     * Sendcloud module service point. getFromCart() returns what the test set with
     * Stubs::returns('SendcloudServicePoint::getFromCart', ...); otherwise a point
     * whose getDetails() reads SendcloudServicePoint::$details[cart id].
     */
    class SendcloudServicePoint
    {
        use Configurable;

        /** @var array<int, object> cart id => point details returned by getDetails() */
        public static $details = [];

        /** @var int */
        public $cartId;

        public static function getFromCart($cartId)
        {
            if (array_key_exists('SendcloudServicePoint::getFromCart', Stubs::$returns)) {
                return Stubs::call('SendcloudServicePoint::getFromCart', [$cartId]);
            }
            Stubs::$calls[] = ['SendcloudServicePoint::getFromCart', [$cartId]];
            $point = new self();
            $point->cartId = (int) $cartId;

            return $point;
        }

        public function getDetails()
        {
            if (array_key_exists('getDetails', $this->stubbed)) {
                return $this->__call('getDetails', []);
            }

            return self::$details[$this->cartId] ?? null;
        }
    }
}

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------

if (!class_exists('DbQuery')) {
    class DbQuery
    {
        /** @var array<string, array<int, string>> */
        public $parts = ['select' => [], 'from' => [], 'join' => [], 'where' => [], 'groupBy' => [], 'orderBy' => [], 'limit' => []];

        public function select($fields)
        {
            $this->parts['select'][] = $fields;

            return $this;
        }

        public function from($table, $alias = null)
        {
            $this->parts['from'][] = '`' . _DB_PREFIX_ . $table . '`' . ($alias ? ' ' . $alias : '');

            return $this;
        }

        public function join($join)
        {
            $this->parts['join'][] = $join;

            return $this;
        }

        public function leftJoin($table, $alias = null, $on = null)
        {
            return $this->join('LEFT JOIN `' . _DB_PREFIX_ . $table . '`' . ($alias ? ' ' . $alias : '') . ($on ? ' ON ' . $on : ''));
        }

        public function innerJoin($table, $alias = null, $on = null)
        {
            return $this->join('INNER JOIN `' . _DB_PREFIX_ . $table . '`' . ($alias ? ' ' . $alias : '') . ($on ? ' ON ' . $on : ''));
        }

        public function where($condition)
        {
            $this->parts['where'][] = $condition;

            return $this;
        }

        public function groupBy($fields)
        {
            $this->parts['groupBy'][] = $fields;

            return $this;
        }

        public function orderBy($fields)
        {
            $this->parts['orderBy'][] = $fields;

            return $this;
        }

        public function limit($limit, $offset = 0)
        {
            $this->parts['limit'] = [$offset ? $offset . ', ' . $limit : (string) $limit];

            return $this;
        }

        public function build()
        {
            $sql = 'SELECT ' . ($this->parts['select'] ? implode(', ', $this->parts['select']) : '*')
                . ' FROM ' . implode(', ', $this->parts['from']);
            if ($this->parts['join']) {
                $sql .= ' ' . implode(' ', $this->parts['join']);
            }
            if ($this->parts['where']) {
                $sql .= ' WHERE (' . implode(') AND (', $this->parts['where']) . ')';
            }
            if ($this->parts['groupBy']) {
                $sql .= ' GROUP BY ' . implode(', ', $this->parts['groupBy']);
            }
            if ($this->parts['orderBy']) {
                $sql .= ' ORDER BY ' . implode(', ', $this->parts['orderBy']);
            }
            if ($this->parts['limit']) {
                $sql .= ' LIMIT ' . $this->parts['limit'][0];
            }

            return $sql;
        }

        public function __toString()
        {
            return $this->build();
        }
    }
}

if (!class_exists('Db')) {
    /**
     * Records every query; results come from Stubs::db() responders.
     * insert()/update()/delete() answer to a pseudo-SQL like "INSERT INTO `ps_table` {json}".
     */
    class Db
    {
        public const INSERT = 1;
        public const INSERT_IGNORE = 2;
        public const REPLACE = 3;
        public const ON_DUPLICATE_KEY = 4;

        /** @var Db|null */
        private static $instance;

        /** @var int */
        private $lastId = 0;

        public static function getInstance($master = true)
        {
            if (!self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function execute($sql, $useCache = true)
        {
            return Stubs::dbResult('execute', $sql, [], true);
        }

        public function executeS($sql, $array = true, $useCache = true)
        {
            return Stubs::dbResult('executeS', $sql, [], []);
        }

        public function getValue($sql, $useCache = true)
        {
            return Stubs::dbResult('getValue', $sql, [], false);
        }

        public function getRow($sql, $useCache = true)
        {
            return Stubs::dbResult('getRow', $sql, [], false);
        }

        public function insert($table, $data, $nullValues = false, $useCache = true, $type = self::INSERT, $addPrefix = true)
        {
            $result = Stubs::dbResult('insert', 'INSERT INTO `' . ($addPrefix ? _DB_PREFIX_ : '') . $table . '` ' . json_encode($data), [$table, $data, $type], true);
            if ($result) {
                $this->lastId = Stubs::$nextId++;
            }

            return $result;
        }

        public function update($table, $data, $where = '', $limit = 0, $nullValues = false, $useCache = true, $addPrefix = true)
        {
            return Stubs::dbResult('update', 'UPDATE `' . ($addPrefix ? _DB_PREFIX_ : '') . $table . '` ' . json_encode($data) . ' WHERE ' . $where, [$table, $data, $where], true);
        }

        public function delete($table, $where = '', $limit = 0, $useCache = true, $addPrefix = true)
        {
            return Stubs::dbResult('delete', 'DELETE FROM `' . ($addPrefix ? _DB_PREFIX_ : '') . $table . '` WHERE ' . $where, [$table, $where], true);
        }

        public function Insert_ID()
        {
            return $this->lastId;
        }

        public function escape($string, $htmlOk = false, $bqSql = false)
        {
            return addslashes((string) $string);
        }

        public function getMsgError()
        {
            return '';
        }

        public function numRows()
        {
            return 0;
        }

        public static function reset(): void
        {
            self::$instance = null;
        }
    }
}

if (!function_exists('pSQL')) {
    function pSQL($string, $htmlOK = false)
    {
        return addslashes((string) $string);
    }
}

if (!function_exists('bqSQL')) {
    function bqSQL($string)
    {
        return str_replace('`', '\`', pSQL($string));
    }
}

// ---------------------------------------------------------------------------
// Request context
// ---------------------------------------------------------------------------

if (!class_exists('Cookie')) {
    #[AllowDynamicProperties]
    class Cookie
    {
        use Configurable;

        /** @var array<string, mixed> */
        public $values = [];

        /** @var int */
        public $writes = 0;

        public function __get($key)
        {
            return $this->values[$key] ?? false;
        }

        public function __set($key, $value)
        {
            $this->values[$key] = $value;
        }

        public function __isset($key)
        {
            return isset($this->values[$key]);
        }

        public function __unset($key)
        {
            unset($this->values[$key]);
        }

        public function exists()
        {
            return true;
        }

        public function write()
        {
            ++$this->writes;
        }

        public function logout()
        {
            $this->values = [];
        }
    }
}

if (!class_exists('Link')) {
    class Link
    {
        use Configurable;

        public function getModuleLink($module, $controller = 'default', array $params = [], $ssl = null, $idLang = null, $idShop = null, $relativeProtocol = false)
        {
            return 'https://shop.test/module/' . $module . '/' . $controller . ($params ? '?' . http_build_query($params) : '');
        }

        public function getPageLink($controller, $ssl = null, $idLang = null, $request = null, $requestUrlEncode = false, $idShop = null, $relativeProtocol = false)
        {
            $query = is_array($request) ? http_build_query($request) : (string) $request;

            return 'https://shop.test/' . $controller . ($query !== '' ? '?' . $query : '');
        }

        public function getAdminLink($controller, $withToken = true, $sfRouteParams = [], $params = [])
        {
            $query = array_merge((array) $sfRouteParams, (array) $params);

            return 'https://shop.test/admin/' . $controller . ($query ? '?' . http_build_query($query) : '');
        }

        public function getImageLink($name, $ids, $type = null, $extension = 'jpg')
        {
            return 'https://shop.test/img/' . $ids . ($type ? '-' . $type : '') . '/' . $name . '.' . $extension;
        }

        public function getProductLink($product, $alias = null, $category = null, $ean13 = null, $idLang = null, $idShop = null, $idProductAttribute = null)
        {
            return 'https://shop.test/product/' . (is_object($product) ? $product->id : $product);
        }

        public function getBaseLink($idShop = null, $ssl = null, $relativeProtocol = false)
        {
            return 'https://shop.test/';
        }
    }
}

if (!class_exists('Smarty')) {
    class Smarty
    {
        use Configurable;

        /** @var array<string, mixed> */
        public $assigned = [];

        public function assign($var, $value = null)
        {
            if (is_array($var)) {
                $this->assigned = array_merge($this->assigned, $var);
            } else {
                $this->assigned[$var] = $value;
            }

            return $this;
        }

        public function getTemplateVars($name = null)
        {
            return $name === null ? $this->assigned : ($this->assigned[$name] ?? null);
        }

        public function fetch($template = null, $cacheId = null, $compileId = null, $parent = null)
        {
            if (array_key_exists('fetch', $this->stubbed)) {
                return $this->__call('fetch', func_get_args());
            }
            Stubs::$calls[] = ['Smarty->fetch', [$template]];

            return 'rendered:' . (is_string($template) ? basename($template) : '');
        }
    }
}

if (!class_exists('Context')) {
    #[AllowDynamicProperties]
    class Context
    {
        use Configurable;

        public $currency;
        public $shop;
        public $language;
        public $country;
        public $cookie;
        public $link;
        public $cart;
        public $customer;
        public $controller;
        public $smarty;
        public $employee;
        public $currentLocale;
        public $mode = 0;

        private static $instance;

        public static function getContext()
        {
            if (!self::$instance) {
                self::$instance = new self();
                self::$instance->currency = new Currency();
            }

            return self::$instance;
        }

        /** A fresh context with working defaults for the objects module code dereferences. */
        public static function reset(): void
        {
            $context = new self();
            $context->currency = new Currency();
            $context->currency->id = 1;
            $context->shop = new Shop();
            $context->language = new Language();
            $context->language->id = 1;
            $context->language->iso_code = 'en';
            $context->country = new Country();
            $context->country->id = 0;
            $context->cookie = new Cookie();
            $context->link = new Link();
            $context->smarty = new Smarty();
            self::$instance = $context;
        }

        public static function setInstanceForTesting($context): void
        {
            self::$instance = $context;
        }

        public function getComputingPrecision()
        {
            return 2;
        }

        public function getCurrentLocale()
        {
            return $this->currentLocale;
        }

        public function isMobile()
        {
            return false;
        }

        public function updateCustomer($customer)
        {
            $this->customer = $customer;
            Stubs::$calls[] = ['Context->updateCustomer', [$customer]];
        }
    }
}

// ---------------------------------------------------------------------------
// Modules and controllers
// ---------------------------------------------------------------------------

if (!class_exists('Module')) {
    #[AllowDynamicProperties]
    class Module
    {
        use Configurable;

        /** @var array<string, object> */
        public static $instances = [];

        public $name = 'buckaroo3';
        public $id = 1;
        public $version = '0.0.0';
        public $active = true;
        public $author = 'Buckaroo';
        public $tab = 'payments_gateways';
        public $displayName = '';
        public $description = '';
        public $context;
        public $_path = '/modules/buckaroo3/';
        public $local_path;

        public function __construct($name = null, $context = null)
        {
            $this->context = $context ?: Context::getContext();
            $this->local_path = _PS_MODULE_DIR_ . $this->name . '/';
        }

        public static function getInstanceByName($name)
        {
            return self::$instances[$name] ?? false;
        }

        public static function isEnabled($name)
        {
            return (bool) Stubs::call('Module::isEnabled', [$name], true);
        }

        public static function isInstalled($name)
        {
            return (bool) Stubs::call('Module::isInstalled', [$name], true);
        }

        /** Mirrors Buckaroo3::getModuleContext(); Module::$context is protected in the core. */
        public function getModuleContext()
        {
            return $this->context;
        }

        public function l($string, $specific = false, $locale = null)
        {
            return $string;
        }

        public function trans($id, array $parameters = [], $domain = null, $locale = null)
        {
            return strtr($id, $parameters);
        }

        public function install()
        {
            return (bool) Stubs::call('Module->install', [], true);
        }

        public function uninstall()
        {
            return (bool) Stubs::call('Module->uninstall', [], true);
        }

        public function registerHook($hookName, $shopList = null)
        {
            return (bool) Stubs::call('Module->registerHook', [$hookName], true);
        }

        public function unregisterHook($hookId, $shopList = null)
        {
            return (bool) Stubs::call('Module->unregisterHook', [$hookId], true);
        }

        public function display($file, $template, $cacheId = null, $compileId = null)
        {
            Stubs::$calls[] = ['Module->display', [$file, $template]];

            return 'display:' . $template;
        }

        public function fetch($templatePath, $cacheId = null, $compileId = null)
        {
            Stubs::$calls[] = ['Module->fetch', [$templatePath]];

            return 'fetch:' . basename((string) $templatePath);
        }

        public function getPathUri()
        {
            return $this->_path;
        }

        public function getLocalPath()
        {
            return $this->local_path;
        }

        public function isRegisteredInHook($hook)
        {
            return (bool) Stubs::call('Module->isRegisteredInHook', [$hook], false);
        }
    }
}

if (!class_exists('PaymentModule')) {
    class PaymentModule extends Module
    {
        public $currentOrder;
        public $currentOrderReference;
        public $currencies = true;
        public $currencies_mode = 'checkbox';

        public function validateOrder($idCart, $idOrderState, $amountPaid, $paymentMethod = 'Unknown', $message = null, $extraVars = [], $currencySpecial = null, $dontTouchAmount = false, $secureKey = false, $shop = null, ?string $orderReference = null)
        {
            $result = Stubs::call('PaymentModule->validateOrder', func_get_args(), true);
            if ($result && !$this->currentOrder) {
                $this->currentOrder = Stubs::$nextId++;
            }

            return $result;
        }

        public static function getInstalledPaymentModules()
        {
            return Stubs::call('PaymentModule::getInstalledPaymentModules', [], []);
        }
    }
}

if (!class_exists('Controller')) {
    #[AllowDynamicProperties]
    abstract class Controller
    {
        use Configurable;

        public $context;
        public $ajax = false;
        public $errors = [];
        public $warning = [];
        public $success = [];
        public $info = [];
        public $php_self;

        /** @var string|null template passed to setTemplate() */
        public $template;

        /** @var array<int, mixed> payloads passed to ajaxRender() */
        public $ajaxOutput = [];

        /** @var array<int, array> registered JS/CSS assets */
        public $assets = [];

        public function __construct()
        {
            $this->context = Context::getContext();
        }

        public function init()
        {
        }

        public function initContent()
        {
        }

        public function postProcess()
        {
        }

        public function setTemplate($template, $params = [], $locale = null)
        {
            $this->template = $template;
        }

        public function ajaxRender($value = null, $controller = null, $method = null)
        {
            $this->ajaxOutput[] = $value;
        }

        public function ajaxDie($value = null, $controller = null, $method = null)
        {
            $this->ajaxOutput[] = $value;

            throw new BuckarooTest\TerminateException($value);
        }

        public function registerJavascript($id, $path, $params = [])
        {
            $this->assets[] = ['js', $id, $path, $params];
        }

        public function registerStylesheet($id, $path, $params = [])
        {
            $this->assets[] = ['css', $id, $path, $params];
        }

        public function addJS($path, $checkPath = true)
        {
            $this->assets[] = ['js', $path];
        }

        public function addCSS($path, $media = 'all', $offset = null, $checkPath = true)
        {
            $this->assets[] = ['css', $path];
        }

        public function addJqueryPlugin($plugin, $path = null, $css = true)
        {
            $this->assets[] = ['jquery', $plugin];
        }

        public function getContainer()
        {
            return Stubs::call('Controller->getContainer', [], null);
        }
    }
}

if (!class_exists('FrontController')) {
    class FrontController extends Controller
    {
        public $display_column_left = true;
        public $display_column_right = true;
        public $ssl = true;
        public $page_name;

        public function redirectWithNotifications($url)
        {
            Stubs::$calls[] = ['FrontController->redirectWithNotifications', [$url]];
            throw new RedirectException((string) $url);
        }

        public function getTemplateVarPage()
        {
            return [];
        }

        public function getBreadcrumbLinks()
        {
            return ['links' => []];
        }
    }
}

if (!class_exists('ModuleFrontController')) {
    class ModuleFrontController extends FrontController
    {
        public $module;

        public function __construct()
        {
            parent::__construct();
            $this->module = Module::getInstanceByName('buckaroo3');
            $this->page_name = 'module-buckaroo3-' . strtolower(static::class);
        }
    }
}

if (!class_exists('AdminControllerCore')) {
    class AdminControllerCore extends Controller
    {
        public $module;
        public $token = 'admintoken';
        public $bootstrap = false;
        public $confirmations = [];
        public $informations = [];
        public $content = '';
        public $meta_title = '';
        public $toolbar_title = '';
        public $page_header_toolbar_btn = [];

        public function __construct()
        {
            parent::__construct();
            $this->module = Module::getInstanceByName('buckaroo3');
        }

        public function l($string, $class = null, $addslashes = false, $htmlentities = true)
        {
            return $string;
        }

        public function trans($id, array $parameters = [], $domain = null, $local = null)
        {
            return strtr($id, $parameters);
        }

        public function renderView()
        {
            return '';
        }

        public function initPageHeaderToolbar()
        {
        }
    }
}

if (!class_exists('AdminController')) {
    class AdminController extends AdminControllerCore
    {
    }
}

if (!class_exists('ModuleAdminController')) {
    class ModuleAdminController extends AdminController
    {
    }
}
