<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ([
            'countries' => ['uuid'],
            'users' => ['uuid'],
            'tenants' => ['uuid', 'user_id', 'document_prefix', 'creation_key', 'creation_hash'],
            'domains' => ['uuid'],
        ] as $table => $columns) {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $condition = implode(' OR ', array_map(fn (string $column): string => "OLD.{$column} IS NOT NEW.{$column}", $columns));
                DB::unprepared("CREATE TRIGGER {$table}_immutable_identity BEFORE UPDATE ON {$table} WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'Immutable central identity'); END");
            } else {
                $condition = implode(' OR ', array_map(fn (string $column): string => "NOT (OLD.{$column} <=> NEW.{$column})", $columns));
                DB::unprepared("CREATE TRIGGER {$table}_immutable_identity BEFORE UPDATE ON {$table} FOR EACH ROW BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable central identity'; END IF; END");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['countries', 'users', 'tenants', 'domains'] as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_immutable_identity");
        }
    }
};
