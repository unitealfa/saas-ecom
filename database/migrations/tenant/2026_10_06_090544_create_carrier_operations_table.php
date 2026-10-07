<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('return_id')->nullable();
            $table->unsignedBigInteger('revision_id')->nullable();
            $table->unsignedBigInteger('superseded_by_operation_id')->nullable();
            $table->unsignedBigInteger('triggered_by_id')->nullable();
            $table->unsignedTinyInteger('type')->comment('CarrierOperationTypeEnum: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12');
            $table->string('operation_key');
            $table->json('sanitized_request');
            $table->text('encrypted_personal_request')->nullable();
            $table->char('request_hash', 64)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->dateTime('request_expires_at', 6)->nullable();
            $table->dateTime('request_purged_at', 6)->nullable();
            $table->string('merchant_reference')->nullable();
            $table->string('adapter_version');
            $table->json('technical_result')->nullable();
            $table->unsignedTinyInteger('status')->default(1)->comment('CarrierOperationStatusEnum: 1, 2, 3, 4, 5, 6, 7, 8');
            $table->integer('attempts_count');
            $table->dateTime('next_attempt_at', 6)->nullable();
            $table->dateTime('ended_at', 6)->nullable();
            $table->dateTime('sending_started_at', 6)->nullable();
            $table->dateTime('superseded_at', 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_operations');
    }
};
