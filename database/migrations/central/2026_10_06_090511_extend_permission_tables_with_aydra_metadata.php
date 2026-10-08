<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');

        Schema::table($tables['permissions'], function (Blueprint $table): void {
            $table->uuid('uuid')->after('id');
            $table->string('label');
            $table->string('feature_code')->nullable();
        });

        Schema::table($tables['roles'], function (Blueprint $table): void {
            $table->uuid('uuid')->after('id');
            $table->char('permission_signature')->charset('ascii')->collation('ascii_bin');
            $table->string('label');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_protected')->default(false);
            $table->boolean('is_super_admin')->default(false);
            $table->unsignedTinyInteger('super_admin_slot')->nullable()->storedAs('CASE WHEN is_super_admin = TRUE THEN 1 ELSE NULL END')->unique();
            $table->unsignedBigInteger('permission_version')->default(1);

            $table->unique(['guard_name', 'permission_signature'], 'uq_roles_bf8a2fab06');
        });

        Schema::table($tables['role_has_permissions'], function (Blueprint $table): void {
            $table->unsignedSmallInteger('duration_days')->default(9999);
        });

        Schema::table($tables['model_has_roles'], function (Blueprint $table): void {
            $table->dateTime('assigned_at');
        });

        Schema::table($tables['model_has_permissions'], function (Blueprint $table): void {
            $table->dateTime('assigned_at');
            $table->dateTime('expires_at');
        });
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        Schema::table($tables['model_has_permissions'], function (Blueprint $table): void {
            $table->dropColumn(['assigned_at', 'expires_at']);
        });

        Schema::table($tables['model_has_roles'], function (Blueprint $table): void {
            $table->dropColumn('assigned_at');
        });

        Schema::table($tables['role_has_permissions'], function (Blueprint $table): void {
            $table->dropColumn('duration_days');
        });

        Schema::table($tables['roles'], function (Blueprint $table): void {
            $table->dropUnique('uq_roles_bf8a2fab06');
            $table->dropColumn(['uuid', 'super_admin_slot', 'permission_signature', 'label', 'is_system', 'is_protected', 'is_super_admin', 'permission_version']);
        });

        Schema::table($tables['permissions'], function (Blueprint $table): void {
            $table->dropColumn(['uuid', 'label', 'feature_code']);
        });
    }
};
