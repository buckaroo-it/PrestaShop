<?php

namespace BuckarooTest\Unit\Upgrade;

if (!class_exists(UpgradeApplePayModule::class, false)) {
    /**
     * Module double that has Buckaroo3::installApplePayDomainAssociation(),
     * which upgrade 5.4.0 calls only when the module provides it.
     */
    class UpgradeApplePayModule extends \PaymentModule
    {
        /** @var int */
        public $applePayInstalls = 0;

        public function installApplePayDomainAssociation()
        {
            ++$this->applePayInstalls;

            return true;
        }
    }
}
