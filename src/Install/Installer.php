<?php

namespace Buckaroo\Src\Install;

use Buckaroo\Src\Config\Config;
use Buckaroo\Src\Repository\CountryRepository;
use Buckaroo\Src\Repository\OrderingRepository;
use Buckaroo\Src\Repository\PaymentMethodRepository;

class Installer implements InstallerInterface
{
    public const FILE_NAME = 'Installer';

    /**
     * @var array
     */
    private $errors = [];

    /**
     * @var \Buckaroo3
     */
    private $module;

    private $symContainer;

    /**
     * @var DatabaseTableInstaller
     */
    private $databaseTableInstaller;

    public function __construct(
        DatabaseTableInstaller $databaseTableInstaller
    ) {
        $this->setContainer();

        $this->module = \Module::getInstanceByName('buckaroo3');

        $this->databaseTableInstaller = $databaseTableInstaller;
    }

    public function install()
    {
        foreach (self::getHooks() as $hook) {
            if (version_compare(_PS_VERSION_, '1.7.0.0', '>=') && 'displayPaymentEU' === $hook) {
                continue;
            }

            try {
                $this->module->registerHook($hook);
            } catch (\Exception $e) {
                $this->errors[] = $this->module->l('Unable to install hook' . $e, self::FILE_NAME);

                return false;
            }
        }

        try {
            $this->initConfig();
        } catch (\Exception $e) {
            $this->errors[] = $this->module->l('Unable to install config', self::FILE_NAME);

            return false;
        }

        $this->installSpecificTabs();

        $this->copyEmailTemplates();
        $this->databaseTableInstaller->install();

        $countryRepository = new CountryRepository();
        $countryRepository->insertCountries();

        $paymentMethodRepository = new PaymentMethodRepository();
        $paymentMethodRepository->insertPaymentMethods();

        $orderingRepository = new OrderingRepository();
        $orderingRepository->insertCountryOrdering();

        return true;
    }

    public function installSpecificTabs(): void
    {
        $this->installTab('AdminBuckaroo_B', 'IMPROVE', 'Buckaroo Payments', true, 'buckaroo');
        $this->installTab('AdminBuckaroo', 'AdminBuckaroo_B', 'Configure', true);
        $this->installTab('AdminBuckaroolog', 'AdminBuckaroo_B', 'Logs', true);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public static function getHooks()
    {
        return [
            'displayHeader',
            'paymentReturn',
            'paymentOptions',
            'displayAdminOrderMainBottom',
            'displayOrderConfirmation',
            'actionEmailSendBefore',
            'displayPDFInvoice',
            'displayBackOfficeHeader',
            'displayBeforeCarrier',
            'actionAdminCustomersListingFieldsModifier',
            'displayAdminProductsMainStepLeftColumnMiddle',
            'displayProductExtraContent',
        ];
    }

    /**
     * @return void
     */
    protected function initConfig()
    {
        \Configuration::updateValue(Config::BUCKAROO_TEST, 1);
        \Configuration::updateValue(Config::BUCKAROO_MERCHANT_KEY, '');
        \Configuration::updateValue(Config::BUCKAROO_SECRET_KEY, '');
        \Configuration::updateValue(Config::BUCKAROO_TRANSACTION_LABEL, '');
        \Configuration::updateValue(Config::BUCKAROO_TRANSACTION_FEE, '');

        \Configuration::updateValue(Config::BUCKAROO_IDEAL_MODE, 'off');
        \Configuration::updateValue(Config::BUCKAROO_IDEAL_LABEL, '');
        \Configuration::updateValue(Config::BUCKAROO_IDEAL_FEE, '');
        \Configuration::updateValue(Config::BUCKAROO_IDEAL_MIN_VALUE, '');
        \Configuration::updateValue(Config::BUCKAROO_IDEAL_MAX_VALUE, '');
        \Configuration::updateValue(Config::BUCKAROO_IDEAL_DISPLAY_TYPE, 'radio');

        \Configuration::updateValue(Config::BUCKAROO_PAYBYBANK_MODE, 'off');
        \Configuration::updateValue(Config::BUCKAROO_PAYBYBANK_LABEL, '');
        \Configuration::updateValue(Config::BUCKAROO_PAYBYBANK_MIN_VALUE, '');
        \Configuration::updateValue(Config::BUCKAROO_PAYBYBANK_MAX_VALUE, '');
        \Configuration::updateValue(Config::BUCKAROO_PAYBYBANK_DISPLAY_TYPE, 'radio');

        \Configuration::updateValue(Config::BUCKAROO_PAYPAL_MODE, 'off');
        \Configuration::updateValue(Config::BUCKAROO_PAYPAL_SELLER_PROTECTION_ENABLED, '0');
        \Configuration::updateValue(Config::BUCKAROO_PAYPAL_LABEL, '');
        \Configuration::updateValue(Config::BUCKAROO_PAYPAL_FEE, '');
        \Configuration::updateValue(Config::BUCKAROO_PAYPAL_MIN_VALUE, '');
        \Configuration::updateValue(Config::BUCKAROO_PAYPAL_MAX_VALUE, '');

        \Configuration::updateValue('BUCKAROO_SDD_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_SDD_LABEL', '');
        \Configuration::updateValue('BUCKAROO_SDD_FEE', '');
        \Configuration::updateValue('BUCKAROO_SDD_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_SDD_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_GIROPAY_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_GIROPAY_LABEL', '');
        \Configuration::updateValue('BUCKAROO_GIROPAY_FEE', '');
        \Configuration::updateValue('BUCKAROO_GIROPAY_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_GIROPAY_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_KBC_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_KBC_LABEL', '');
        \Configuration::updateValue('BUCKAROO_KBC_FEE', '');
        \Configuration::updateValue('BUCKAROO_KBC_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_KBC_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_EPS_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_EPS_LABEL', '');
        \Configuration::updateValue('BUCKAROO_EPS_FEE', '');
        \Configuration::updateValue('BUCKAROO_EPS_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_EPS_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_PRZELEWY24_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_PRZELEWY24_LABEL', '');
        \Configuration::updateValue('BUCKAROO_PRZELEWY24_FEE', '');
        \Configuration::updateValue('BUCKAROO_PRZELEWY24_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_PRZELEWY24_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_TRUSTLY_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_TRUSTLY_LABEL', '');
        \Configuration::updateValue('BUCKAROO_TRUSTLY_FEE', '');
        \Configuration::updateValue('BUCKAROO_TRUSTLY_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_TRUSTLY_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_TINKA_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_TINKA_LABEL', '');
        \Configuration::updateValue('BUCKAROO_TINKA_FEE', '');
        \Configuration::updateValue('BUCKAROO_TINKA_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_TINKA_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_PAYPEREMAIL_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_PAYPEREMAIL_LABEL', '');
        \Configuration::updateValue('BUCKAROO_PAYPEREMAIL_FEE', '');
        \Configuration::updateValue('BUCKAROO_PAYPEREMAIL_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_PAYPEREMAIL_MAX_VALUE', '');
        \Configuration::updateValue('BUCKAROO_PAYPEREMAIL_SEND_EMAIL', '1');
        \Configuration::updateValue('BUCKAROO_PAYPEREMAIL_EXPIRE_DAYS', '7');
        \Configuration::updateValue('BUCKAROO_PAYPEREMAIL_ALLOWED_METHODS', 'ideal');

        \Configuration::updateValue('BUCKAROO_PAYCONIQ_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_PAYCONIQ_LABEL', '');
        \Configuration::updateValue('BUCKAROO_PAYCONIQ_FEE', '');
        \Configuration::updateValue('BUCKAROO_PAYCONIQ_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_PAYCONIQ_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_MISTERCASH_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_MISTERCASH_LABEL', '');
        \Configuration::updateValue('BUCKAROO_MISTERCASH_FEE', '');
        \Configuration::updateValue('BUCKAROO_MISTERCASH_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_MISTERCASH_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_BANCONTACT_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_BANCONTACT_LABEL', '');
        \Configuration::updateValue('BUCKAROO_BANCONTACT_FEE', '');
        \Configuration::updateValue('BUCKAROO_BANCONTACT_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_BANCONTACT_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_GIFTCARD_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_GIFTCARD_LABEL', '');
        \Configuration::updateValue('BUCKAROO_GIFTCARD_FEE', '');
        \Configuration::updateValue('BUCKAROO_GIFTCARD_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_GIFTCARD_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_CREDITCARD_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_CREDITCARD_LABEL', '');
        \Configuration::updateValue('BUCKAROO_CREDITCARD_FEE', '');
        \Configuration::updateValue('BUCKAROO_CREDITCARD_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_CREDITCARD_MAX_VALUE', '');
        \Configuration::updateValue('BUCKAROO_CREDITCARD_DISPLAY_TYPE', 'radio');

        \Configuration::updateValue('BUCKAROO_SOFORTBANKING_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_SOFORTBANKING_LABEL', '');
        \Configuration::updateValue('BUCKAROO_SOFORTBANKING_FEE', '');
        \Configuration::updateValue('BUCKAROO_SOFORTBANKING_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_SOFORTBANKING_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_SOFORT_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_SOFORT_LABEL', '');
        \Configuration::updateValue('BUCKAROO_SOFORT_FEE', '');
        \Configuration::updateValue('BUCKAROO_SOFORT_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_SOFORT_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_BELFIUS_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_BELFIUS_LABEL', '');
        \Configuration::updateValue('BUCKAROO_BELFIUS_FEE', '');
        \Configuration::updateValue('BUCKAROO_BELFIUS_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_BELFIUS_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_TRANSFER_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_TRANSFER_LABEL', '');
        \Configuration::updateValue('BUCKAROO_TRANSFER_FEE', '');
        \Configuration::updateValue('BUCKAROO_TRANSFER_DATEDUE', '14');
        \Configuration::updateValue('BUCKAROO_TRANSFER_SENDMAIL', '0');
        \Configuration::updateValue('BUCKAROO_TRANSFER_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_TRANSFER_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_AFTERPAY_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_AFTERPAY_LABEL', '');
        \Configuration::updateValue('BUCKAROO_AFTERPAY_FEE', '');
        \Configuration::updateValue('BUCKAROO_AFTERPAY_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_AFTERPAY_MAX_VALUE', '');
        \Configuration::updateValue('BUCKAROO_AFTERPAY_DEFAULT_VAT', '2');
        \Configuration::updateValue('BUCKAROO_AFTERPAY_WRAPPING_VAT', '2');
        \Configuration::updateValue('BUCKAROO_AFTERPAY_TAXRATE', serialize([]));
        \Configuration::updateValue('BUCKAROO_AFTERPAY_CUSTOMER_TYPE', 'both');

        \Configuration::updateValue('BUCKAROO_KLARNA_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_KLARNA_LABEL', '');
        \Configuration::updateValue('BUCKAROO_KLARNA_FEE', '');
        \Configuration::updateValue('BUCKAROO_KLARNA_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_KLARNA_MAX_VALUE', '');
        \Configuration::updateValue('BUCKAROO_KLARNA_DEFAULT_VAT', '2');
        \Configuration::updateValue('BUCKAROO_KLARNA_WRAPPING_VAT', '2');
        \Configuration::updateValue('BUCKAROO_KLARNA_TAXRATE', serialize([]));

        \Configuration::updateValue('BUCKAROO_APPLEPAY_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_APPLEPAY_LABEL', '');
        \Configuration::updateValue('BUCKAROO_APPLEPAY_FEE', '');
        \Configuration::updateValue('BUCKAROO_APPLEPAY_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_APPLEPAY_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_IN3_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_IN3_LABEL', '');
        \Configuration::updateValue('BUCKAROO_IN3_API_VERSION', 'V3');
        \Configuration::updateValue('BUCKAROO_IN3_PAYMENT_LOGO', 'in3');
        \Configuration::updateValue('BUCKAROO_IN3_FEE', '');
        \Configuration::updateValue('BUCKAROO_IN3OLD_FEE', '');
        \Configuration::updateValue('BUCKAROO_IN3_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_IN3_MAX_VALUE', '');

        \Configuration::updateValue('BUCKAROO_BILLINK_MODE', 'off');
        \Configuration::updateValue('BUCKAROO_BILLINK_LABEL', '');
        \Configuration::updateValue('BUCKAROO_BILLINK_FEE', '');
        \Configuration::updateValue('BUCKAROO_BILLINK_MIN_VALUE', '');
        \Configuration::updateValue('BUCKAROO_BILLINK_MAX_VALUE', '');
        \Configuration::updateValue('BUCKAROO_BILLINK_DEFAULT_VAT', '2');
        \Configuration::updateValue('BUCKAROO_BILLINK_WRAPPING_VAT', '2');
        \Configuration::updateValue('BUCKAROO_BILLINK_TAXRATE', serialize([]));
        \Configuration::updateValue('BUCKAROO_BILLINK_CUSTOMER_TYPE', 'both');

        \Configuration::updateValue('BUCKAROO_GLOBAL_POSITION', 0);
        \Configuration::updateValue('BUCKAROO_PAYBYBANK_POSITION', 1);
        \Configuration::updateValue('BUCKAROO_PAYPAL_POSITION', 2);
        \Configuration::updateValue('BUCKAROO_SDD_POSITION', 3);
        \Configuration::updateValue('BUCKAROO_IDEAL_POSITION', 4);
        \Configuration::updateValue('BUCKAROO_GIROPAY_POSITION', 5);
        \Configuration::updateValue('BUCKAROO_KBC_POSITION', 6);
        \Configuration::updateValue('BUCKAROO_EPS_POSITION', 7);
        \Configuration::updateValue('BUCKAROO_PAYPEREMAIL_POSITION', 8);
        \Configuration::updateValue('BUCKAROO_PAYCONIQ_POSITION', 9);
        \Configuration::updateValue('BUCKAROO_PRZELEWY24_POSITION', 10);
        \Configuration::updateValue('BUCKAROO_TINKA_POSITION', 11);
        \Configuration::updateValue('BUCKAROO_TRUSTLY_POSITION', 12);
        \Configuration::updateValue('BUCKAROO_MISTERCASH_POSITION', 13);
        \Configuration::updateValue('BUCKAROO_GIFTCARD_POSITION', 14);
        \Configuration::updateValue('BUCKAROO_CREDITCARD_POSITION', 15);
        \Configuration::updateValue('BUCKAROO_SOFORTBANKING_POSITION', 16);
        \Configuration::updateValue('BUCKAROO_TRANSFER_POSITION', 17);
        \Configuration::updateValue('BUCKAROO_AFTERPAY_POSITION', 18);
        \Configuration::updateValue('BUCKAROO_APPLEPAY_POSITION', 19);
        \Configuration::updateValue('BUCKAROO_KLARNA_POSITION', 20);
        \Configuration::updateValue('BUCKAROO_BELFIUS_POSITION', 21);
        \Configuration::updateValue('BUCKAROO_IN3_POSITION', 22);
        \Configuration::updateValue('BUCKAROO_BILLINK_POSITION', 23);
        \Configuration::updateValue('BUCKAROO_IDIN_POSITION', 24);
    }

    public function installTab($className, $parent, $name, $active = true, $icon = '')
    {
        $idParent = is_int($parent) ? $parent : \Tab::getIdFromClassName($parent);

        $moduleTab = new \Tab();
        $moduleTab->class_name = $className;
        $moduleTab->id_parent = $idParent;
        $moduleTab->module = $this->module->name;
        $moduleTab->active = $active;
        $moduleTab->icon = $icon; /** @phpstan-ignore-line */
        $languages = \Language::getLanguages(true);
        foreach ($languages as $language) {
            $moduleTab->name[$language['id_lang']] = $name;
        }

        if (!$moduleTab->save()) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function copyEmailTemplates()
    {
        $source = _PS_ROOT_DIR_ . '/modules/buckaroo3/classes/Mail.php';
        $destinationDir = _PS_ROOT_DIR_ . '/override/classes/';
        $destinationFile = $destinationDir . 'Mail.php';

        // Check if destination directory exists, create it if necessary
        if (!is_dir($destinationDir)) {
            if (!mkdir($destinationDir, 0755, true)) {
                throw new \Exception("Failed to create destination directory '{$destinationDir}'");
            }
        }

        // Attempt to copy the file
        if (!copy($source, $destinationFile)) {
            throw new \Exception("Failed to copy file from '{$source}' to '{$destinationFile}'");
        }
    }

    private function setContainer()
    {
        global $kernel;

        if (!$kernel) {
            require_once _PS_ROOT_DIR_ . '/app/AppKernel.php';
            $kernel = new \AppKernel('prod', false);
            $kernel->boot();
        }
        $this->symContainer = $kernel->getContainer();
    }
}
