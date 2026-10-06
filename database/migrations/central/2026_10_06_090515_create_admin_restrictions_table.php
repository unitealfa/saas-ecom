<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_restrictions', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('target_tenant_id')->nullable();
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->unsignedBigInteger('target_role_id')->nullable();
            $table->unsignedBigInteger('created_by_id');
            $table->unsignedTinyInteger('effect')->comment('RestrictionEffectEnum: 1, 2');
            $table->unsignedTinyInteger('status')->default(1)->comment('OverrideStatusEnum: 1, 2, 3, 4');
            $table->dateTime('started_at', 6);
            $table->dateTime('ended_at', 6)->nullable();
            $table->string('normalized_target_type', 16)->nullable(false)->storedAs('CASE WHEN target_tenant_id IS NOT NULL THEN \'tenant\' WHEN target_user_id IS NOT NULL THEN \'user\' WHEN target_role_id IS NOT NULL THEN \'role\' ELSE \'global\' END');
            $table->unsignedBigInteger('normalized_target_id')->nullable(false)->storedAs('COALESCE(target_tenant_id, target_user_id, target_role_id, 0)');
            $table->unsignedTinyInteger('active_slot')->nullable()->storedAs('CASE WHEN status = 1 AND deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->dateTime('expires_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_restrictions');
    }
};
