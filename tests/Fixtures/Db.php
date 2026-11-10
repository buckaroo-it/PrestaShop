<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this file
 *
 *  @author    Buckaroo.nl <plugins@buckaroo.nl>
 *  @copyright Copyright (c) Buckaroo B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/*
 * Minimal in-memory Db shared by the unit tests (loaded from tests/bootstrap.php):
 * auto-increment tables with the simple WHERE clauses the repositories use, and
 * executeS() rows for the refund tests.
 */

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}
if (!defined('_MYSQL_ENGINE_')) {
    define('_MYSQL_ENGINE_', 'InnoDB');
}

if (!function_exists('pSQL')) {
    function pSQL($string, $htmlOK = false)
    {
        return addslashes((string) $string);
    }
}

if (!class_exists('PrestaShopLogger')) {
    class PrestaShopLogger
    {
        /** @var array<int, string> */
        public static $messages = [];

        public static function addLog($message, $severity = 1)
        {
            self::$messages[] = (string) $message;

            return true;
        }
    }
}

if (!class_exists('Db')) {
    class Db
    {
        /** @var self|null */
        private static $instance;

        /** @var array<int,array<string,string>> Rows returned by executeS(); overridable by tests. */
        public static $giftcards = [
            ['code' => 'boekenbon'],
            ['code' => 'fashionucadeaukaart'],
            ['code' => 'fashioncheque'],
            ['code' => 'vvvgiftcard'],
            ['code' => 'webshopgiftcard'],
            ['code' => 'digitalebioscoopbon'],
            ['code' => 'yourgift'],
        ];

        /** @var array<string, array<int, array<string, mixed>>> */
        public $tables = [];

        /** @var int */
        private $lastId = 0;

        public static function getInstance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public static function reset(): void
        {
            self::$instance = new self();
        }

        public function executeS($query)
        {
            return self::$giftcards;
        }

        public function execute($sql)
        {
            return true;
        }

        public function insert($table, $data)
        {
            $id = count($this->tables[$table] ?? []) + 1;
            $this->tables[$table][$id] = array_merge(['id_bk_payment_attempt' => $id, 'reference' => null, 'amount' => 0, 'transaction_key' => '', 'id_order' => 0, 'amount_mismatch' => 0], $data);
            $this->lastId = $id;

            return true;
        }

        public function Insert_ID()
        {
            return $this->lastId;
        }

        public function update($table, $data, $where)
        {
            $updated = false;
            foreach ($this->matching($table, $where) as $id) {
                $this->tables[$table][$id] = array_merge($this->tables[$table][$id], $data);
                $updated = true;
            }

            return $updated;
        }

        public function getRow($sql)
        {
            if (!preg_match('/FROM `?' . preg_quote(_DB_PREFIX_, '/') . '(\w+)`? WHERE (.+)$/s', $sql, $m)) {
                return false;
            }

            $ids = $this->matching($m[1], $m[2]);
            if (stripos($m[2], 'DESC') !== false) {
                rsort($ids);
            }

            return $ids ? $this->tables[$m[1]][$ids[0]] : false;
        }

        /**
         * @return int[]
         */
        private function matching(string $table, string $where): array
        {
            $where = preg_replace('/\s+ORDER BY.*$/s', '', $where);
            preg_match('/(\w+)\s*=\s*\'?([^\']*)\'?/', (string) $where, $m);
            $ids = [];
            foreach ($this->tables[$table] ?? [] as $id => $row) {
                if ((string) ($row[$m[1]] ?? '') === stripslashes($m[2])) {
                    $ids[] = $id;
                }
            }

            return $ids;
        }
    }
}
