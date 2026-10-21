<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_permission_templates')) {
            return;
        }

        DB::transaction(function (): void {
            $snapshot = [];
            foreach (['roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'role_permission_templates'] as $table) {
                $snapshot[$table] = DB::table($table)->lockForUpdate()->get()->map(fn ($row) => (array) $row)->all();
            }

            $conflicts = DB::table('role_permission_templates as source')
                ->join('role_has_permissions as target', fn ($join) => $join
                    ->on('target.role_id', '=', 'source.role_id')
                    ->on('target.permission_id', '=', 'source.permission_id'))
                ->whereColumn('source.duration_days', '<>', 'target.duration_days')->exists();
            if ($conflicts) {
                throw new LogicException('Conflicting role permission durations; no data has been replaced.');
            }

            if ($snapshot['role_permission_templates'] !== []) {
                $backup = 'authorization-transitions/role-permissions-'.Str::uuid().'.enc';
                if (! Storage::disk('local')->put($backup, Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR)))) {
                    throw new LogicException('The private authorization backup could not be written.');
                }
            }

            foreach ($snapshot['role_permission_templates'] as $grant) {
                $key = ['role_id' => $grant['role_id'], 'permission_id' => $grant['permission_id']];
                if (! DB::table('role_has_permissions')->where($key)->exists()) {
                    DB::table('role_has_permissions')->insert([...$key, 'duration_days' => $grant['duration_days']]);
                }
                if ((int) DB::table('role_has_permissions')->where($key)->value('duration_days') !== (int) $grant['duration_days']) {
                    throw new LogicException('A role permission was not copied exactly.');
                }
            }
        });

        // MySQL DDL commits implicitly; run this migration with application writes paused.
        Schema::drop('role_permission_templates');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new LogicException('Restore a reviewed private backup instead of deleting current role permissions.');
    }
};
