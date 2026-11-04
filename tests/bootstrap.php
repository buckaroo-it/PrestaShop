<?php

/**
 * Simple PHPUnit bootstrap for the Buckaroo PrestaShop module.
 *
 * Inspired by the Mollie PrestaShop test setup:
 * https://github.com/mollie/PrestaShop/tree/master/tests
 */

// Fake PrestaShop constants so module classes don't early-exit
if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '9.0.1.0');
}

// The legacy checkout/payment classes include their dependencies through
// _PS_MODULE_DIR_ . 'buckaroo3/...'. The unit tests replace those dependencies
// with the lightweight stubs below, so the constant must point to an isolated,
// empty directory: if it pointed at the real modules directory the real
// classes would be declared next to the stubs ("Cannot declare class Checkout").
if (!defined('_PS_MODULE_DIR_')) {
    $buckarooTestModuleDir = sys_get_temp_dir() . '/buckaroo3-phpunit-modules/';
    if (!is_dir($buckarooTestModuleDir)) {
        @mkdir($buckarooTestModuleDir, 0777, true);
    }
    if (!file_exists($buckarooTestModuleDir . 'buckaroo3')) {
        @symlink(realpath(__DIR__ . '/..'), $buckarooTestModuleDir . 'buckaroo3');
    }
    define('_PS_MODULE_DIR_', $buckarooTestModuleDir);
}

// CoreLogger writes below _PS_ROOT_DIR_ . '/var/logs/buckaroo3/'. Keep the log
// files the tests produce out of any real tree. The legacy api/ files require
// the Composer autoloader through _PS_ROOT_DIR_ . '/modules/buckaroo3/vendor/autoload.php',
// so that path gets a stub pointing at the real autoloader (written on every run,
// before any test file is loaded).
if (!defined('_PS_ROOT_DIR_')) {
    $buckarooTestRootDir = sys_get_temp_dir() . '/buckaroo3-phpunit-root';
    $buckarooTestVendorStub = $buckarooTestRootDir . '/modules/buckaroo3/vendor';
    if (!is_dir($buckarooTestVendorStub)) {
        @mkdir($buckarooTestVendorStub, 0777, true);
    }
    file_put_contents(
        $buckarooTestVendorStub . '/autoload.php',
        '<?php return require_once ' . var_export(realpath(__DIR__ . '/../vendor/autoload.php'), true) . ';'
    );
    define('_PS_ROOT_DIR_', $buckarooTestRootDir);
}

// Shop base URI (root installation in tests; '/shop/' when installed in a subdirectory)
if (!defined('__PS_BASE_URI__')) {
    define('__PS_BASE_URI__', '/');
}

// Make sure we are in the module root when running tests
chdir(__DIR__ . '/..');

// Remaining PrestaShop constants the module code reads.
foreach ([
    '_DB_PREFIX_' => 'ps_',
    '_MYSQL_ENGINE_' => 'InnoDB',
    '_PS_MODE_DEV_' => false,
    '_COOKIE_KEY_' => 'buckaroo-test-cookie-key',
    '_MODULE_DIR_' => '/modules/',
    '_PS_MAIL_DIR_' => sys_get_temp_dir() . '/buckaroo3-phpunit-mails/',
] as $buckarooConstant => $buckarooValue) {
    if (!defined($buckarooConstant)) {
        define($buckarooConstant, $buckarooValue);
    }
}
unset($buckarooConstant, $buckarooValue);

/*
 * PrestaShop core, Symfony and Doctrine stubs (see tests/Support/Stubs.php for
 * how tests configure them). The real module files are loaded through
 * _PS_MODULE_DIR_ . 'buckaroo3/', so no module class may be stubbed here.
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Support/Stubs.php';
require_once __DIR__ . '/Support/PrestaShopStubs.php';
require_once __DIR__ . '/Support/FrameworkStubs.php';
require_once __DIR__ . '/Support/TestCase.php';
Context::reset();

// ---- Namespaced PaymentOption stub ----
if (!class_exists('\PrestaShop\PrestaShop\Core\Payment\PaymentOption')) {
    eval('
    namespace PrestaShop\PrestaShop\Core\Payment;

    class PaymentOption
    {
        private $moduleName;
        private $callToActionText;
        private $action;
        private $inputs = [];
        private $form;
        private $logo;

        public function setCallToActionText($text)
        {
            $this->callToActionText = $text;
            return $this;
        }

        public function setAction($action)
        {
            $this->action = $action;
            return $this;
        }

        public function setModuleName($name)
        {
            $this->moduleName = $name;
            return $this;
        }

        public function setInputs(array $inputs)
        {
            $this->inputs = $inputs;
            return $this;
        }

        public function setForm($form)
        {
            $this->form = $form;
            return $this;
        }

        public function setLogo($logo)
        {
            $this->logo = $logo;
            return $this;
        }

        public function getModuleName()
        {
            return $this->moduleName;
        }

        public function getCallToActionText()
        {
            return $this->callToActionText;
        }

        public function getAction()
        {
            return $this->action;
        }

        public function getForm()
        {
            return $this->form;
        }

        public function getInputs()
        {
            return $this->inputs;
        }

        public function getLogo()
        {
            return $this->logo;
        }
    }
    ');
}

// ---- Minimal Doctrine ORM EntityManager stub ----
