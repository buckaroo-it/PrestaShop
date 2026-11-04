<?php
/**
 * Doubles that extend module classes (Buckaroo3, Logger, BuckarooCommonController).
 * Loaded by loadControllers() once the module files are loaded.
 */

namespace BuckarooTest\Unit\Controller\Front\Request;

if (!class_exists(RequestModuleDouble::class)) {
    /**
     * The real Buckaroo3 class (so `instanceof Buckaroo3` checks hold) without
     * its constructor; fee and payment mode answers come from public fields,
     * services from the `get` stub.
     */
    class RequestModuleDouble extends \Buckaroo3
    {
        /** @var array|null what getBuckarooFee() returns */
        public $fee;

        /** @var bool what isPaymentModeActive() returns */
        public $paymentModeActive = true;

        /** @var array<string, object> module container services by id */
        public $services = [];

        public function __construct()
        {
            $this->context = \Context::getContext();
            $this->id = 77;
            $this->name = 'buckaroo3';
            $this->version = '5.4.0';
            $this->author = 'Buckaroo';
        }

        public function get($serviceId)
        {
            \BuckarooTest\Stubs::$calls[] = ['RequestModuleDouble->get', [$serviceId]];
            if (!array_key_exists($serviceId, $this->services)) {
                throw new \RuntimeException('Service ' . $serviceId . ' not found');
            }

            return $this->services[$serviceId];
        }

        public function getBuckarooFee($payment_method, $percentageBaseAmount = null)
        {
            \BuckarooTest\Stubs::$calls[] = ['RequestModuleDouble->getBuckarooFee', [$payment_method, $percentageBaseAmount]];

            return $this->fee;
        }

        public function isPaymentModeActive($method)
        {
            return $this->paymentModeActive;
        }
    }
}

if (!class_exists(RecordingLogger::class)) {
    /** Keeps log lines in memory instead of writing log files. */
    class RecordingLogger extends \Logger
    {
        /** @var array<int, array{0: string, 1: mixed, 2: mixed}> [level, info, description] */
        public $entries = [];

        public function __construct()
        {
            parent::__construct(\Logger::INFO, 'request-test');
        }

        public function logInfo($info, $descr = null)
        {
            $this->entries[] = ['info', $info, $descr];
        }

        public function logError($info, $descr = null)
        {
            $this->entries[] = ['error', $info, $descr];
        }

        public function logWarn($info, $descr = null)
        {
            $this->entries[] = ['warn', $info, $descr];
        }

        public function logDebug($info, $descr = null)
        {
            $this->entries[] = ['debug', $info, $descr];
        }

        /** @return string[] messages logged at $level */
        public function messages(string $level): array
        {
            $found = [];
            foreach ($this->entries as $entry) {
                if ($entry[0] === $level) {
                    $found[] = (string) $entry[1];
                }
            }

            return $found;
        }

        /** @return mixed description of the first entry whose message is $info */
        public function descriptionOf(string $info)
        {
            foreach ($this->entries as $entry) {
                if ($entry[1] === $info) {
                    return $entry[2];
                }
            }

            return null;
        }
    }
}

if (!class_exists(ExposedCommonController::class)) {
    /** Makes the protected helpers of BuckarooCommonController callable from tests. */
    class ExposedCommonController extends \BuckarooCommonController
    {
        public function call(string $method, array $args = [])
        {
            return $this->{$method}(...$args);
        }
    }
}
