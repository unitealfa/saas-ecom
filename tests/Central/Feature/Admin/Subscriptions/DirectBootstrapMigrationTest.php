<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('fresh central schema creates separate subscriptions and installments without conversions', function (): void {
    expect(Schema::hasTable('subscriptions'))->toBeTrue()
        ->and(Schema::hasTable('subscription_installments'))->toBeTrue()
        ->and(Schema::hasTable('subscription_requests'))->toBeTrue()
        ->and(Schema::hasTable('admin_restrictions'))->toBeFalse()
        ->and(Schema::hasColumn('subscriptions', 'record_type'))->toBeFalse()
        ->and(Schema::hasColumn('subscriptions', 'parent_subscription_id'))->toBeFalse()
        ->and(Schema::hasColumn('subscription_installments', 'subscription_id'))->toBeTrue()
        ->and(Schema::hasColumn('saas_invoices', 'subscription_record_type'))->toBeFalse()
        ->and(Schema::hasColumn('saas_invoices', 'installment_record_type'))->toBeFalse()
        ->and(Schema::hasColumn('subscription_requests', 'subscription_record_type'))->toBeFalse();

    foreach ([
        '2026_10_06_090515_create_admin_restrictions_table.php',
        '2026_11_09_160000_split_subscription_installments.php',
        '2026_11_10_000000_drop_admin_restrictions_table.php',
    ] as $obsoleteMigration) {
        expect(file_exists(database_path('migrations/central/'.$obsoleteMigration)))->toBeFalse();
    }

    $foreignKeys = static fn (string $table): array => Schema::getForeignKeys($table);
    $hasForeignKey = static function (string $table, array $columns, string $referenced, array $referencedColumns) use ($foreignKeys): bool {
        foreach ($foreignKeys($table) as $foreign) {
            if ($foreign['columns'] === $columns
                && $foreign['foreign_table'] === $referenced
                && $foreign['foreign_columns'] === $referencedColumns
                && $foreign['on_delete'] === 'restrict'
                && $foreign['on_update'] === 'restrict') {
                return true;
            }
        }
        return false;
    };

    expect($hasForeignKey('subscription_installments', ['subscription_id', 'user_id'], 'subscriptions', ['id', 'user_id']))->toBeTrue()
        ->and($hasForeignKey('saas_invoices', ['installment_id'], 'subscription_installments', ['id']))->toBeTrue()
        ->and($hasForeignKey('saas_invoices', ['installment_id', 'subscription_id', 'user_id'], 'subscription_installments', ['id', 'subscription_id', 'user_id']))->toBeTrue()
        ->and($hasForeignKey('saas_invoices', ['subscription_id', 'user_id'], 'subscriptions', ['id', 'user_id']))->toBeTrue()
        ->and($hasForeignKey('subscription_requests', ['current_subscription_id', 'user_id'], 'subscriptions', ['id', 'user_id']))->toBeTrue();

    $database = DB::connection()->getDatabaseName();
    $checkNames = DB::table('information_schema.table_constraints')
        ->where('constraint_schema', $database)
        ->where('constraint_type', 'CHECK')
        ->selectRaw('CONSTRAINT_NAME AS constraint_name')
        ->pluck('constraint_name')->all();
    expect($checkNames)->toContain('ck_subscriptions_parent_required')
        ->toContain('ck_subscription_installments_shape')
        ->not->toContain('ck_subscriptions_cbccd3efcf');
    $triggerNames = DB::table('information_schema.triggers')
        ->where('trigger_schema', $database)
        ->selectRaw('TRIGGER_NAME AS trigger_name')
        ->pluck('trigger_name')->all();
    expect($triggerNames)->toContain('guard_subscription_installments_identity')
        ->not->toContain('guard_admin_restrictions_e2fc712050');
});
