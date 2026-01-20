<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

/**
 * Shared test doubles used across BuckarooPaymentService unit tests.
 */

if (!class_exists(BuckarooPaymentServiceTestable::class)) {
    /**
     * Testable subclass that only initialises the pieces of BuckarooPaymentService
     * that we need for unit testing configuration-based logic.
     */
    class BuckarooPaymentServiceTestable extends BuckarooPaymentService
    {
        public function __construct(FakeBuckarooConfigService $buckarooConfigService)
        {
            // We do not call the real constructor to avoid PrestaShop/Doctrine dependencies.
            $this->buckarooConfigService = $buckarooConfigService;
        }
    }
}

if (!class_exists(BuckarooPaymentServiceConditionsTestable::class)) {
    /**
     * Testable subclass exposing additional protected behaviour and replacing
     * address lookups with in-memory mocks.
     */
    class BuckarooPaymentServiceConditionsTestable extends BuckarooPaymentService
    {
        /** @var array<int,object> */
        private array $addressesById = [];

        public function __construct(FakeBuckarooConfigService $buckarooConfigService)
        {
            $this->buckarooConfigService = $buckarooConfigService;
        }

        public function setMockAddress(int $id, int $countryId, string $company = '', string $address1 = 'Main 1'): void
        {
            $this->addressesById[$id] = (object) [
                'id_country' => $countryId,
                'company' => $company,
                'address1' => $address1,
            ];
        }

        protected function getAddressById($id)
        {
            return $this->addressesById[(int) $id] ?? null;
        }

        public function publicIsIn3Available($cart): bool
        {
            return $this->isIn3Available($cart);
        }

        public function publicIsAfterpayAvailable($cart): bool
        {
            return $this->isAfterpayAvailable($cart);
        }
    }
}

if (!class_exists(BuckarooPaymentServiceFlowTestable::class)) {
    /**
     * Testable subclass for exercising the full getPaymentOptions flow with
     * configurable in-memory dependencies.
     */
    class BuckarooPaymentServiceFlowTestable extends BuckarooPaymentService
    {
        /** @var array<int,object> */
        private array $addressesById = [];

        /** @var bool */
        private $idinValid = false;

        public function __construct(
            $paymentMethodRepository,
            $orderingRepository,
            $countryRepository,
            FakeBuckarooConfigService $buckarooConfigService,
            $buckarooFeeService,
            $issuersPayByBank,
            $capayableIn3,
            $context,
            $module
        ) {
            $this->paymentMethodRepository = $paymentMethodRepository;
            $this->bkOrderingRepository = $orderingRepository;
            $this->countryRepository = $countryRepository;
            $this->buckarooConfigService = $buckarooConfigService;
            $this->buckarooFeeService = $buckarooFeeService;
            $this->issuersPayByBank = $issuersPayByBank;
            $this->capayableIn3 = $capayableIn3;
            $this->context = $context;
            $this->module = $module;
        }

        public function setMockAddress(int $id, int $countryId, string $company = '', string $address1 = 'Main 1'): void
        {
            $this->addressesById[$id] = (object) [
                'id_country' => $countryId,
                'company' => $company,
                'address1' => $address1,
            ];
        }

        protected function getAddressById($id)
        {
            return $this->addressesById[(int) $id] ?? null;
        }

        public function setIdinValid(bool $valid): void
        {
            $this->idinValid = $valid;
        }

        public function isCustomerIdinValid($cart)
        {
            return $this->idinValid;
        }

        /**
         * Expose context for fine-grained manipulation in specific tests when needed.
         */
        public function getContext()
        {
            return $this->context;
        }
    }
}

if (!class_exists(BuckarooPaymentServiceGetOptionsTestable::class)) {
    /**
     * Testable subclass for exercising getPaymentOptions with fully mocked dependencies.
     */
    class BuckarooPaymentServiceGetOptionsTestable extends BuckarooPaymentService
    {
        public function __construct(
            $paymentMethodRepository,
            $orderingRepository,
            $countryRepository,
            FakeBuckarooConfigService $buckarooConfigService,
            $buckarooFeeService,
            $issuersPayByBank,
            $capayableIn3,
            $context,
            $module
        ) {
            $this->paymentMethodRepository = $paymentMethodRepository;
            $this->bkOrderingRepository = $orderingRepository;
            $this->countryRepository = $countryRepository;
            $this->buckarooConfigService = $buckarooConfigService;
            $this->buckarooFeeService = $buckarooFeeService;
            $this->issuersPayByBank = $issuersPayByBank;
            $this->capayableIn3 = $capayableIn3;
            $this->context = $context;
            $this->module = $module;
        }
    }
}

if (!class_exists(FakeBuckarooConfigService::class)) {
    /**
     * Very small fake of the BuckarooConfigService, exposing only the methods our
     * tests need from BuckarooPaymentService.
     */
    class FakeBuckarooConfigService
    {
        /** @var array<string, array<string, mixed>> */
        private array $configByMethod;

        /**
         * @param array<string, array<string, mixed>> $configByMethod
         */
        public function __construct(array $configByMethod)
        {
            $this->configByMethod = $configByMethod;
        }

        public function getConfigArrayForMethod(string $method): ?array
        {
            return $this->configByMethod[$method] ?? null;
        }

        public function getConfigValue(string $method, string $key)
        {
            $config = $this->getConfigArrayForMethod($method);

            return $config[$key] ?? null;
        }
    }
}


