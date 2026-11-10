<?php

use Illuminate\Support\Facades\DB;

test('the existing MySQL central and tenant databases match the documented migrations', function (): void {
    $database = getenv('AYDRA_AUDIT_CENTRAL_DATABASE');
    if (! is_string($database) || ! preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $database)) {
        throw new LogicException('Specify the existing central database explicitly for this read-only audit.');
    }

    $connection = config('database.connections.mysql');
    $connection['database'] = $database;
    config(['database.connections.live_schema_audit' => $connection]);
    $default = DB::getDefaultConnection();

    try {
        DB::setDefaultConnection('live_schema_audit');
        $tenants = DB::table('tenants')->whereNull('deleted_at')->get(['id', 'uuid', 'data']);
        $databases = [['central', $database]];
        foreach ($tenants as $tenant) {
            $data = json_decode($tenant->data, true, flags: JSON_THROW_ON_ERROR);
            $tenantDatabase = $data['tenancy_db_name'] ?? null;

            if (! is_string($tenantDatabase) || $tenantDatabase === '') {
                throw new LogicException('The read-only audit requires the persisted tenant database name.');
            }
            $databases[] = ['tenant', $tenantDatabase];
        }

        $additionalDatabases = getenv('AYDRA_AUDIT_TENANT_DATABASES');

        if (is_string($additionalDatabases) && $additionalDatabases !== '') {
            foreach (explode(',', $additionalDatabases) as $name) {
                $name = trim($name);

                if (! preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $name) || $name === $database) {
                    throw new LogicException('Additional audit targets must be explicit tenant database names.');
                }

                if (! in_array($name, array_column($databases, 1), true)) {
                    $databases[] = ['tenant', $name];
                }
            }
        }

        foreach ($databases as [$context, $database]) {
            $connection['database'] = $database;
            config(['database.connections.live_schema_audit' => $connection]);
            DB::purge('live_schema_audit');
            assertDocumentedDatabaseSchema($context);

            $expected = array_map(fn (string $file): string => basename($file, '.php'), glob(database_path('migrations/'.$context.'/*.php')));
            sort($expected);
            // Ces trois anciennes migrations existent encore dans la table
            // migrations d'une base déjà mise à niveau, mais ne font plus partie
            // du baseline pour les installations neuves.
            $historical = [
                '2026_10_06_090515_create_admin_restrictions_table',
                '2026_11_09_160000_split_subscription_installments',
                '2026_11_10_000000_drop_admin_restrictions_table',
            ];
            $applied = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
            expect(array_values(array_diff($applied, $historical)))->toBe($expected);
            expect(DB::table('information_schema.triggers')->where('trigger_schema', $database)->count())->toBe($context === 'central' ? 78 : 205);
            expect(DB::table('information_schema.table_constraints')->where('constraint_schema', $database)->where('constraint_type', 'CHECK')->where('enforced', 'YES')->count())->toBe($context === 'central' ? 79 : 151);
        }
    } finally {
        DB::setDefaultConnection($default);
        DB::purge('live_schema_audit');
    }
})->skip(fn (): bool => getenv('AYDRA_AUDIT_EXISTING_DATABASES') !== '1', 'Opt in to auditing existing MySQL databases with AYDRA_AUDIT_EXISTING_DATABASES=1.');
