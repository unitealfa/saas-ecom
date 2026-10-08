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
            $table->uuid('uuid');
            $table->foreignId('provider_id')->constrained('shipping_providers', indexName: 'fk_carrier_operations_b3c7cddea3')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments', indexName: 'fk_carrier_operations_9325aebf7e')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('order_id')->nullable()->constrained('orders', indexName: 'fk_carrier_operations_ca13a6b2c9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('return_id')->nullable()->constrained('order_returns', indexName: 'fk_carrier_operations_4ee7679ca5')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('revision_id')->nullable()->constrained('order_revisions', indexName: 'fk_carrier_operations_e22455d8ca')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('superseded_by_operation_id')->nullable()->constrained('carrier_operations', indexName: 'fk_carrier_operations_41c5e1821d')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('triggered_by_id')->nullable()->constrained('users', indexName: 'fk_carrier_operations_176faee833')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('type')->comment('CarrierOperationTypeEnum: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12');
            $table->string('operation_key');
            $table->json('sanitized_request');
            $table->text('encrypted_personal_request')->nullable();
            $table->char('request_hash')->charset('ascii')->collation('ascii_bin');
            $table->dateTime('request_expires_at')->nullable();
            $table->dateTime('request_purged_at')->nullable();
            $table->string('merchant_reference')->nullable();
            $table->string('adapter_version');
            $table->json('technical_result')->nullable();
            $table->unsignedTinyInteger('status')->default(1)->comment('CarrierOperationStatusEnum: 1, 2, 3, 4, 5, 6, 7, 8');
            $table->integer('attempts_count');
            $table->dateTime('next_attempt_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->dateTime('sending_started_at')->nullable();
            $table->dateTime('superseded_at')->nullable();
            $table->timestamps();

            $table->unique(['operation_key'], 'uq_carrier_operations_c8ff3469da');
            $table->index(['status', 'next_attempt_at'], 'ix_carrier_operations_bef5422393');
            $table->index(['shipment_id', 'status'], 'ix_carrier_operations_002c46552c');
            $table->index(['revision_id', 'order_id'], 'ix_carrier_operations_ff6828c755');
            $table->index(['shipment_id', 'order_id'], 'ix_carrier_operations_4066108b3c');
            $table->index(['request_expires_at'], 'ix_carrier_operations_772a03f7ab');
            $table->index(['provider_id'], 'ix_carrier_operations_b3c7cddea3');
            $table->index(['order_id'], 'ix_carrier_operations_ca13a6b2c9');
            $table->index(['return_id'], 'ix_carrier_operations_4ee7679ca5');
            $table->index(['superseded_by_operation_id'], 'ix_carrier_operations_41c5e1821d');
            $table->index(['triggered_by_id'], 'ix_carrier_operations_176faee833');
            $table->foreign(['revision_id', 'order_id'], 'fk_carrier_operations_ff6828c755')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipment_id', 'order_id'], 'fk_carrier_operations_4066108b3c')->references(['id', 'order_id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_operations');
    }
};
