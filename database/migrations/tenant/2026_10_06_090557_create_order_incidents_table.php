<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('order_id')->constrained('orders', indexName: 'fk_order_incidents_ca13a6b2c9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('shipment_id')->constrained('shipments', indexName: 'fk_order_incidents_9325aebf7e')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('shipped_revision_id')->constrained('order_revisions', indexName: 'fk_order_incidents_f48a5da11a')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('order_item_id')->unique()->constrained('order_items', indexName: 'fk_order_incidents_f63d0d3350')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('return_id')->nullable()->constrained('order_returns', indexName: 'fk_order_incidents_4ee7679ca5')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('opened_by_id')->nullable()->constrained('users', indexName: 'fk_order_incidents_db5c37fda7')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('validated_by_id')->nullable()->constrained('users', indexName: 'fk_order_incidents_6a16de7f77')->restrictOnDelete()->restrictOnUpdate();
            $table->string('operation_key')->unique();
            $table->integer('affected_quantity');
            $table->decimal('eligible_product_amount');
            $table->decimal('eligible_shipping_amount');
            $table->unsignedTinyInteger('status')->default(1)->comment('IncidentStatusEnum: 1, 2, 3, 4, 5, 6');
            $table->text('reason');
            $table->dateTime('validated_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'order_id'], 'uq_order_incidents_cc60f48a93');
            $table->index(['shipment_id', 'order_id', 'shipped_revision_id'], 'ix_order_incidents_b5f82f238f');
            $table->index(['order_id', 'status'], 'ix_order_incidents_58978f474d');
            $table->index(['order_item_id', 'shipped_revision_id'], 'ix_order_incidents_14ba853e98');
            $table->index(['return_id', 'shipment_id'], 'ix_order_incidents_a663f454eb');
            $table->index(['shipped_revision_id'], 'ix_order_incidents_f48a5da11a');
            $table->index(['opened_by_id'], 'ix_order_incidents_db5c37fda7');
            $table->index(['validated_by_id'], 'ix_order_incidents_6a16de7f77');
            $table->foreign(['shipment_id', 'order_id', 'shipped_revision_id'], 'fk_order_incidents_b5f82f238f')->references(['id', 'order_id', 'shipped_revision_id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id', 'shipped_revision_id'], 'fk_order_incidents_14ba853e98')->references(['id', 'revision_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['return_id', 'shipment_id'], 'fk_order_incidents_a663f454eb')->references(['id', 'shipment_id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_incidents');
    }
};
