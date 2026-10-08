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
            $table->uuid('uuid');
            $table->foreignId('shipment_id')->constrained('shipments', indexName: 'fk_shipment_events_9325aebf7e')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('actor_id')->nullable()->constrained('users', indexName: 'fk_shipment_events_fe4b4e1602')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('logistics_status')->nullable()->comment('ShipmentStatusEnum: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11');
            $table->unsignedTinyInteger('financial_status')->nullable()->comment('CollectionStatusEnum: 1, 2, 3, 4, 5, 6, 7');
            $table->string('external_code')->nullable();
            $table->string('event_type');
            $table->string('raw_external_activity')->nullable();
            $table->string('raw_external_status')->nullable();
            $table->string('adapter_version');
            $table->json('sanitized_external_payload')->nullable();
            $table->char('payload_hash')->charset('ascii')->collation('ascii_bin');
            $table->dateTime('payload_expires_at')->nullable();
            $table->dateTime('payload_purged_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('comment')->nullable();
            $table->string('station')->nullable();
            $table->string('courier_label')->nullable();
            $table->dateTime('next_delivery_attempt_at')->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->dateTime('observed_at');
            $table->unsignedTinyInteger('source')->comment('ShipmentEventSourceEnum: 1, 2, 3');
            $table->string('deduplication_key');
            $table->timestamp('created_at');

            $table->unique(['deduplication_key'], 'uq_shipment_events_5da4dfb656');
            $table->index(['shipment_id', 'observed_at'], 'ix_shipment_events_43ceaaa97d');
            $table->index(['shipment_id', 'occurred_at'], 'ix_shipment_events_af41a9d5c4');
            $table->index(['payload_expires_at'], 'ix_shipment_events_7bc826d864');
            $table->index(['actor_id'], 'ix_shipment_events_fe4b4e1602');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
    }
};
