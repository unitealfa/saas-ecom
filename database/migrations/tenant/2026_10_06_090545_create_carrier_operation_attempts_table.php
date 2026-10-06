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
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('operation_id');
            $table->integer('attempt_number');
            $table->integer('http_status_code')->nullable();
            $table->json('sanitized_response')->nullable();
            $table->dateTime('payload_expires_at', 6)->nullable();
            $table->dateTime('payload_purged_at', 6)->nullable();
            $table->string('error_code')->nullable();
            $table->integer('duration_ms');
            $table->dateTime('started_at', 6);
            $table->dateTime('ended_at', 6)->nullable();
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_operation_attempts');
    }
};
