<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (return_item_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('variant_id')->constrained('product_variants', indexName: 'fk_stock_movements_14f215ed6d')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items', indexName: 'fk_stock_movements_f63d0d3350')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('return_item_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users', indexName: 'fk_stock_movements_fe4b4e1602')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('reversal_of_id')->nullable()->constrained('stock_movements', indexName: 'fk_stock_movements_d046da8d50')->restrictOnDelete()->restrictOnUpdate();
            $table->bigInteger('variant_sequence');
            $table->unsignedTinyInteger('type')->comment('StockMovementTypeEnum: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11');
            $table->integer('physical_delta');
            $table->integer('reserved_delta');
            $table->integer('quarantine_delta');
            $table->integer('return_received_delta');
            $table->integer('return_restocked_delta');
            $table->integer('return_lost_delta');
            $table->integer('return_missing_delta')->default(0);
            $table->integer('physical_before');
            $table->integer('physical_after');
            $table->integer('reserved_before');
            $table->integer('reserved_after');
            $table->integer('quarantine_before');
            $table->integer('quarantine_after');
            $table->decimal('unit_cost_snapshot');
            $table->decimal('loss_amount');
            $table->string('operation_key');
            $table->uuid('correlation_id');
            $table->text('note')->nullable();
            $table->timestamp('created_at');

            $table->unique(['id', 'variant_id'], 'uq_stock_movements_15acc595bd');
            $table->unique(['variant_id', 'variant_sequence'], 'uq_stock_movements_2af188f970');
            $table->unique(['operation_key'], 'uq_stock_movements_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_stock_movements_d046da8d50');
            $table->index(['variant_id', 'created_at', 'id'], 'ix_stock_movements_efb6058ec1');
            $table->index(['return_item_id', 'created_at', 'id'], 'ix_stock_movements_8b6c8dd49b');
            $table->index(['order_item_id', 'variant_id'], 'ix_stock_movements_36a9296d11');
            $table->index(['return_item_id', 'variant_id'], 'ix_stock_movements_a2c9ce4719');
            $table->index(['reversal_of_id', 'variant_id'], 'ix_stock_movements_e6592dc642');
            $table->index(['actor_id'], 'ix_stock_movements_fe4b4e1602');
            $table->foreign(['order_item_id', 'variant_id'], 'fk_stock_movements_36a9296d11')->references(['id', 'variant_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'variant_id'], 'fk_stock_movements_e6592dc642')->references(['id', 'variant_id'])->on('stock_movements')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
