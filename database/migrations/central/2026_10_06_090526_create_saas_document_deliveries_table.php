<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_document_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('proof_media_id')->nullable();
            $table->string('operation_key', 191)->unique();
            $table->unsignedTinyInteger('document_type')->comment('DocumentTypeEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('channel')->comment('DocumentDeliveryChannelEnum: 1, 2, 3, 4');
            $table->text('encrypted_recipient');
            $table->unsignedTinyInteger('delivery_status')->comment('DocumentDeliveryStatusEnum: 1, 2, 3, 4, 5, 6, 7, 8');
            $table->integer('attempts_count');
            $table->json('delivery_attempts')->nullable();
            $table->dateTime('next_attempt_at', 6)->nullable();
            $table->dateTime('sent_at', 6)->nullable();
            $table->dateTime('delivered_at', 6)->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('error_code')->nullable();
            $table->dateTime('sending_started_at', 6)->nullable();
            $table->char('correlation_id', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_document_deliveries');
    }
};
