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
            $table->uuid('uuid');
            $table->foreignId('admin_id')->constrained('users', indexName: 'fk_admin_restrictions_f159b8e5cb')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('permission_id')->constrained('permissions', indexName: 'fk_admin_restrictions_50d0bf2736')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('target_tenant_id')->nullable()->constrained('tenants', indexName: 'fk_admin_restrictions_843f38c8c7')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('target_user_id')->nullable()->constrained('users', indexName: 'fk_admin_restrictions_a28a1cc1dd')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('target_role_id')->nullable()->constrained('roles', indexName: 'fk_admin_restrictions_d88b7f676a')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('created_by_id')->constrained('users', indexName: 'fk_admin_restrictions_6fb667974b')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('effect')->comment('RestrictionEffectEnum: 1, 2');
            $table->unsignedTinyInteger('status')->default(1)->comment('OverrideStatusEnum: 1, 2, 3, 4');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('normalized_target_type')->nullable(false)->storedAs('CASE WHEN target_tenant_id IS NOT NULL THEN \'tenant\' WHEN target_user_id IS NOT NULL THEN \'user\' WHEN target_role_id IS NOT NULL THEN \'role\' ELSE \'global\' END');
            $table->unsignedBigInteger('normalized_target_id')->nullable(false)->storedAs('COALESCE(target_tenant_id, target_user_id, target_role_id, 0)');
            $table->unsignedTinyInteger('active_slot')->nullable()->storedAs('CASE WHEN status = 1 AND deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['admin_id', 'permission_id', 'normalized_target_type', 'normalized_target_id', 'effect', 'active_slot'], 'uq_admin_restrictions_9a8bd22048');
            $table->index(['permission_id'], 'ix_admin_restrictions_50d0bf2736');
            $table->index(['target_tenant_id'], 'ix_admin_restrictions_843f38c8c7');
            $table->index(['target_user_id'], 'ix_admin_restrictions_a28a1cc1dd');
            $table->index(['target_role_id'], 'ix_admin_restrictions_d88b7f676a');
            $table->index(['created_by_id'], 'ix_admin_restrictions_6fb667974b');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_restrictions');
    }
};
