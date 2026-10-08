<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('return_id')->constrained('order_returns', indexName: 'fk_return_items_4ee7679ca5')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('order_item_id')->constrained('order_items', indexName: 'fk_return_items_f63d0d3350')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('shipped_revision_id')->constrained('order_revisions', indexName: 'fk_return_items_f48a5da11a')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('variant_id')->constrained('product_variants', indexName: 'fk_return_items_14f215ed6d')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('inspected_by_id')->nullable()->constrained('users', indexName: 'fk_return_items_e8b4e1ba43')->restrictOnDelete()->restrictOnUpdate();
            $table->integer('expected_quantity');
            $table->integer('received_quantity');
            $table->integer('restocked_quantity');
            $table->integer('lost_quantity');
            $table->integer('quarantined_quantity');
            $table->integer('documented_missing_quantity');
            $table->text('discrepancy_reason')->nullable();
            $table->decimal('unit_cost_snapshot');
            $table->dateTime('inspected_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['id', 'variant_id'], 'uq_return_items_15acc595bd');
            $table->unique(['return_id', 'order_item_id'], 'uq_return_items_feb81fbcb0');
            $table->index(['return_id', 'shipped_revision_id'], 'ix_return_items_aaf597818c');
            $table->index(['order_item_id', 'shipped_revision_id'], 'ix_return_items_14ba853e98');
            $table->index(['order_item_id', 'variant_id'], 'ix_return_items_36a9296d11');
            $table->index(['shipped_revision_id'], 'ix_return_items_f48a5da11a');
            $table->index(['variant_id'], 'ix_return_items_14f215ed6d');
            $table->index(['inspected_by_id'], 'ix_return_items_e8b4e1ba43');
            $table->foreign(['return_id', 'shipped_revision_id'], 'fk_return_items_aaf597818c')->references(['id', 'shipped_revision_id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id', 'shipped_revision_id'], 'fk_return_items_14ba853e98')->references(['id', 'revision_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id', 'variant_id'], 'fk_return_items_36a9296d11')->references(['id', 'variant_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
