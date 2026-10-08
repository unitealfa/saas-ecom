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
            $table->string('operation_key')->nullable()->unique();
            $table->uuid('correlation_id')->nullable();
            $table->unsignedTinyInteger('origin')->comment('ActivityOriginEnum: 1, 2, 3, 4');
            $table->dateTime('performed_at')->nullable();

            $table->index(['log_name', 'performed_at', 'id'], 'ix_activity_log_15c617967d');
            $table->index(['correlation_id'], 'ix_activity_log_c787c4c20c');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex('ix_activity_log_15c617967d');
            $table->dropIndex('ix_activity_log_c787c4c20c');
            $table->dropColumn(['uuid', 'operation_key', 'correlation_id', 'origin', 'performed_at']);
        });
    }
};
