<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_operation_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('operation_id')->constrained('carrier_operations', indexName: 'fk_carrier_operation_attempts_9a9856b253')->restrictOnDelete()->restrictOnUpdate();
            $table->integer('attempt_number');
            $table->integer('http_status_code')->nullable();
            $table->json('sanitized_response')->nullable();
            $table->dateTime('payload_expires_at')->nullable();
            $table->dateTime('payload_purged_at')->nullable();
            $table->string('error_code')->nullable();
            $table->integer('duration_ms');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamp('created_at');

            $table->unique(['operation_id', 'attempt_number'], 'uq_carrier_operation_attempts_5e2bb8e610');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_operation_attempts');
    }
};
