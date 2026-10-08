<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('order_id')->constrained('orders', indexName: 'fk_shipments_ca13a6b2c9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('shipped_revision_id')->constrained('order_revisions', indexName: 'fk_shipments_f48a5da11a')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('provider_id')->constrained('shipping_providers', indexName: 'fk_shipments_b3c7cddea3')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('label_media_id')->nullable()->constrained('media', indexName: 'fk_shipments_b3b4e83a73')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('assigned_by_id')->constrained('users', indexName: 'fk_shipments_bc15dd68ce')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('pickup_point_uuid')->nullable();
            $table->unsignedTinyInteger('delivery_mode')->comment('DeliveryModeEnum: 1, 2');
            $table->unsignedTinyInteger('status')->default(1)->comment('ShipmentStatusEnum: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11');
            $table->string('raw_external_status')->nullable();
            $table->string('tracking')->nullable();
            $table->string('merchant_reference')->nullable();
            $table->string('external_reference')->nullable();
            $table->decimal('cod_amount');
            $table->decimal('estimated_cost');
            $table->decimal('weight_kg')->nullable();
            $table->boolean('is_fragile');
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('carrier_validated_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'order_id', 'shipped_revision_id'], 'uq_shipments_ccccb20e50');
            $table->unique(['id', 'order_id'], 'uq_shipments_cc60f48a93');
            $table->unique(['id', 'provider_id'], 'uq_shipments_b8600d5a3c');
            $table->unique(['order_id'], 'uq_shipments_ca13a6b2c9');
            $table->unique(['provider_id', 'tracking'], 'uq_shipments_58866182c8');
            $table->unique(['provider_id', 'merchant_reference'], 'uq_shipments_e5751a4df4');
            $table->index(['shipped_revision_id', 'order_id', 'delivery_mode'], 'ix_shipments_25270a5a20');
            $table->index(['shipped_revision_id', 'order_id', 'pickup_point_uuid'], 'ix_shipments_d69c160f70');
            $table->index(['provider_id', 'status'], 'ix_shipments_166f81d0c0');
            $table->index(['label_media_id'], 'ix_shipments_b3b4e83a73');
            $table->index(['assigned_by_id'], 'ix_shipments_bc15dd68ce');
            $table->foreign(['shipped_revision_id', 'order_id'], 'fk_shipments_02c54136b3')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipped_revision_id', 'order_id', 'delivery_mode'], 'fk_shipments_25270a5a20')->references(['id', 'order_id', 'delivery_mode'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipped_revision_id', 'order_id', 'pickup_point_uuid'], 'fk_shipments_d69c160f70')->references(['id', 'order_id', 'pickup_point_uuid'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
