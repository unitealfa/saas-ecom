<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('MySQL executes both schemas with the documented types collations and relationships', function (): void {
    $connection = config('database.connections.mysql');
    $connection['database'] = 'information_schema';
    config(['database.connections.schema_admin' => $connection]);
    $created = [];
    $default = DB::getDefaultConnection();

    try {
        foreach (['central', 'tenant'] as $context) {
            $database = 'aydra_migration_test_'.$context.'_'.str_replace('-', '', (string) Str::uuid());
            DB::connection('schema_admin')->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
            $created[] = $database;
            $connection['database'] = $database;
            config(['database.connections.schema_check' => $connection]);
            DB::purge('schema_check');
            DB::setDefaultConnection('schema_check');

            $this->artisan('migrate', ['--database' => 'schema_check', '--path' => database_path('migrations/'.$context), '--realpath' => true, '--no-interaction' => true])->assertSuccessful();

            assertDocumentedDatabaseSchema($context);
            assertDocumentedAuthorizationConstraints($context);
            if ($context === 'central') {
                assertDocumentedCentralGeographyConstraints();
            } else {
                assertDocumentedTenantCatalogAndStockConstraints();
            }

            $this->artisan('migrate:rollback', ['--database' => 'schema_check', '--path' => database_path('migrations/'.$context), '--realpath' => true, '--no-interaction' => true])->assertSuccessful();
            expect(array_column(DB::connection('schema_check')->getSchemaBuilder()->getTables($database), 'name'))->toBe(['migrations']);
        }
    } finally {
        DB::setDefaultConnection($default);
        DB::purge('schema_check');
        foreach ($created as $database) {
            if (! preg_match('/^aydra_migration_test_(central|tenant)_[a-f0-9]{32}$/D', $database)) {
                throw new LogicException('Refusing to drop a database outside this migration test.');
            }
            DB::connection('schema_admin')->statement('DROP DATABASE `'.$database.'`');
        }
        DB::purge('schema_admin');
    }
})->skip(fn (): bool => getenv('AYDRA_TEST_MYSQL_MIGRATIONS') !== '1', 'Opt in to temporary MySQL databases with AYDRA_TEST_MYSQL_MIGRATIONS=1.');
