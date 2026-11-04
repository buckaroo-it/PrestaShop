<?php
/**
 * Shared helpers for the upgrade/upgrade-X.Y.Z.php scripts.
 *
 * The scripts define global upgrade_module_X_Y_Z() functions; each test file
 * loads the scripts it needs with requireUpgrade() and calls the function
 * with a module double.
 */

namespace BuckarooTest\Unit\Upgrade;

use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

abstract class UpgradeTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Fresh Db singleton: Insert_ID() starts at 0 in every test.
        \Db::reset();
    }

    protected function tearDown(): void
    {
        \Db::reset();
        parent::tearDown();
    }

    protected static function requireUpgrade(string $version): void
    {
        require_once dirname(__DIR__, 3) . '/upgrade/upgrade-' . $version . '.php';
    }

    protected function module(): \PaymentModule
    {
        return new \PaymentModule();
    }

    /** @return array<int, string> hook names passed to registerHook(), in order */
    protected static function registeredHooks(): array
    {
        return array_map(function (array $args) {
            return $args[0];
        }, Stubs::callsTo('Module->registerHook'));
    }

    /**
     * Arguments of every Db::update() call on $table.
     *
     * @return array<int, array{0: string, 1: array, 2: string}>
     */
    protected static function updates(string $table): array
    {
        return self::dbCalls('update', $table);
    }

    /** @return array<int, array> arguments of every Db::insert() call on $table */
    protected static function inserts(string $table): array
    {
        return self::dbCalls('insert', $table);
    }

    private static function dbCalls(string $method, string $table): array
    {
        $found = [];
        foreach (Stubs::$queries as $query) {
            if ($query['method'] === $method && $query['args'][0] === $table) {
                $found[] = $query['args'];
            }
        }

        return $found;
    }

    /** SQL passed to execute() that matches $pattern. */
    protected static function executed(string $pattern): array
    {
        return array_values(array_filter(Stubs::sql('execute'), function ($sql) use ($pattern) {
            return (bool) preg_match($pattern, $sql);
        }));
    }

    /**
     * Raw "INSERT INTO ps_bk_payment_methods" statements run through execute()
     * get auto-increment ids starting at $firstId, like MySQL's last insert id.
     */
    protected function autoIncrementPaymentMethods(int $firstId): void
    {
        $next = $firstId;
        Stubs::db('/^INSERT INTO ps_bk_payment_methods /', function () use (&$next) {
            self::setProperty(\Db::getInstance(), 'lastId', $next++);

            return true;
        });
    }

    /** The decoded value json of a raw "INSERT INTO ps_bk_configuration" statement. */
    protected static function configInsertFor(int $paymentMethodId): ?array
    {
        foreach (Stubs::sql('execute') as $sql) {
            if (preg_match('/^INSERT INTO ps_bk_configuration \\(configurable_id, value\\) VALUES \\(' . $paymentMethodId . ", '(.*)'\\)$/s", $sql, $m)) {
                return json_decode(stripslashes($m[1]), true);
            }
        }

        return null;
    }
}
