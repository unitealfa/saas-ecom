<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->uuid('uuid')->after('id');
            $table->foreignId('tenant_id')->nullable()->constrained('tenants', indexName: 'fk_activity_log_759b6ffea8')->restrictOnDelete()->restrictOnUpdate();
            $table->string('operation_key')->nullable()->unique();
            $table->uuid('correlation_id');
            $table->unsignedTinyInteger('origin')->comment('ActivityOriginEnum: 1, 2, 3, 4');

            $table->index(['correlation_id'], 'ix_activity_log_c787c4c20c');
            $table->index(['tenant_id'], 'ix_activity_log_759b6ffea8');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropForeign('fk_activity_log_759b6ffea8');
            $table->dropIndex('ix_activity_log_c787c4c20c');
            $table->dropIndex('ix_activity_log_759b6ffea8');
            $table->dropColumn(['uuid', 'tenant_id', 'operation_key', 'correlation_id', 'origin']);
        });
    }
};
