<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_events', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedTinyInteger('logistics_status')->nullable()->comment('ShipmentStatusEnum: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11');
            $table->unsignedTinyInteger('financial_status')->nullable()->comment('CollectionStatusEnum: 1, 2, 3, 4, 5, 6, 7');
            $table->string('external_code')->nullable();
            $table->string('event_type');
            $table->string('raw_external_activity')->nullable();
            $table->string('raw_external_status')->nullable();
            $table->string('adapter_version');
            $table->json('sanitized_external_payload')->nullable();
            $table->char('payload_hash', 64)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->dateTime('payload_expires_at', 6)->nullable();
            $table->dateTime('payload_purged_at', 6)->nullable();
            $table->text('reason')->nullable();
            $table->text('comment')->nullable();
            $table->string('station')->nullable();
            $table->string('courier_label')->nullable();
            $table->dateTime('next_delivery_attempt_at', 6)->nullable();
            $table->dateTime('occurred_at', 6)->nullable();
            $table->dateTime('observed_at', 6);
            $table->unsignedTinyInteger('source')->comment('ShipmentEventSourceEnum: 1, 2, 3');
            $table->string('deduplication_key');
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
    }
};
