<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use LogicException;
use PDO;

abstract class TestCase extends BaseTestCase
{
    private static ?PDO $testDatabaseAdmin = null;

    private static ?string $testDatabasePrefix = null;

    /** @var array<string, true> */
    private static array $testDatabases = [];

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.connections.mysql');
        if (self::$testDatabaseAdmin === null) {
            self::$testDatabasePrefix = 'aydra_test_'.bin2hex(random_bytes(6)).'_';
            self::$testDatabaseAdmin = new PDO(
                'mysql:host='.$connection['host'].';port='.$connection['port'].';dbname=information_schema;charset=utf8mb4',
                $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
            register_shutdown_function(static function (): void {
                foreach (array_keys(self::$testDatabases) as $database) {
                    self::dropIsolatedMysqlDatabase($database);
                }
            });
        }
        $group = str_contains(static::class, '\\Central\\') ? 'central' : substr(hash('sha256', static::class), 0, 8);
        $database = self::$testDatabasePrefix.'central_'.$group;
        if (! isset(self::$testDatabases[$database])) {
            self::$testDatabaseAdmin->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            self::$testDatabases[$database] = true;
            RefreshDatabaseState::$migrated = false;
        }
        $tables = self::$testDatabaseAdmin->query('SELECT table_name FROM information_schema.tables WHERE table_schema = '.self::$testDatabaseAdmin->quote($database))->fetchAll(PDO::FETCH_COLUMN);
        self::$testDatabaseAdmin->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $table) {
                if ($table !== 'migrations') {
                    self::$testDatabaseAdmin->exec('TRUNCATE TABLE `'.$database.'`.`'.$table.'`');
                }
            }
        } finally {
            self::$testDatabaseAdmin->exec('SET FOREIGN_KEY_CHECKS=1');
        }
        $app['config']->set([
            'database.default' => 'mysql', 'database.connections.mysql.database' => $database,
            'tenancy.database.central_connection' => 'mysql',
            'tenancy.database.prefix' => self::$testDatabasePrefix.'boutique_', 'tenancy.database.suffix' => '',
        ]);
        $app['db']->purge('mysql');

        return $app;
    }

    public static function dropIsolatedMysqlDatabase(string $database): void
    {
        if (self::$testDatabaseAdmin === null || self::$testDatabasePrefix === null
            || ! str_starts_with($database, self::$testDatabasePrefix)
            || ! preg_match('/^aydra_test_[a-f0-9]{12}_[a-zA-Z0-9_-]+$/D', $database)) {
            throw new LogicException('Refusing to remove a database outside this MySQL test run.');
        }
        self::$testDatabaseAdmin->exec('DROP DATABASE IF EXISTS `'.$database.'`');
        unset(self::$testDatabases[$database]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
