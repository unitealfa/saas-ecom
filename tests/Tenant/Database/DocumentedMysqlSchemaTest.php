<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('plain decimal columns preserve large amounts and exact cents on new MySQL connections', function (): void {
    config(['database.connections.decimal_probe' => config('database.connections.mysql')]);

    try {
        foreach (['mysql', 'decimal_probe'] as $connectionName) {
            $connection = DB::connection($connectionName);
            $schema = $connection->getSchemaBuilder();
            $schema->create('amount_probe', function (Blueprint $table): void {
                $table->id();
                $table->decimal('amount');
                $table->decimal('weight_kg', places: 3);
            });

            $connection->table('amount_probe')->insert([
                ['amount' => '999999999999.99', 'weight_kg' => '1.234'],
                ['amount' => '0.01', 'weight_kg' => '0.001'],
            ]);

            expect($connection->table('amount_probe')->orderBy('id')->pluck('amount')->all())->toBe(['999999999999.99', '0.01']);
            expect($connection->table('amount_probe')->orderBy('id')->pluck('weight_kg')->all())->toBe(['1.234', '0.001']);
            $schema->drop('amount_probe');
        }
    } finally {
        DB::purge('decimal_probe');
    }
});

test('plain UUID columns keep public uniqueness without restricting reference UUIDs on new connections', function (): void {
    config(['database.connections.uuid_probe' => config('database.connections.mysql')]);

    try {
        foreach (['mysql', 'uuid_probe'] as $connectionName) {
            $connection = DB::connection($connectionName);
            $schema = $connection->getSchemaBuilder();
            $tableName = 'uuid_probe_'.$connectionName;
            $schema->create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid');
                $table->uuid('reference_uuid');
            });

            $columns = array_column($schema->getColumns($tableName), null, 'name');
            expect($columns['uuid']['type'])->toBe('char(36)');
            expect($columns['uuid']['collation'])->toBe('ascii_bin');
            $referenceUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
            $firstUuid = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
            $connection->table($tableName)->insert([
                ['uuid' => $firstUuid, 'reference_uuid' => $referenceUuid],
                ['uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'reference_uuid' => $referenceUuid],
            ]);

            expect($connection->table($tableName)->where('reference_uuid', $referenceUuid)->count())->toBe(2);
            expect(fn () => $connection->table($tableName)->insert(['uuid' => $firstUuid, 'reference_uuid' => $referenceUuid]))->toThrow(QueryException::class);
            $schema->drop($tableName);
        }
    } finally {
        DB::purge('uuid_probe');
    }
});

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
            expect(DB::table('information_schema.triggers')->where('trigger_schema', $database)->count())->toBe($context === 'central' ? 72 : 205);
            assertDocumentedAuthorizationConstraints($context);
            if ($context === 'central') {
                expect(collect(DB::connection('schema_check')->getSchemaBuilder()->getIndexes('tenants'))->contains(fn (array $index): bool => ! $index['unique'] && $index['columns'] === ['shop_name']))->toBeTrue();
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
