<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('revision_id')->constrained('order_revisions', indexName: 'fk_order_items_e22455d8ca')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('variant_id')->constrained('product_variants', indexName: 'fk_order_items_14f215ed6d')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_id')->constrained('products', indexName: 'fk_order_items_c3adad4f81')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('promotion_id')->nullable()->constrained('product_promotions', indexName: 'fk_order_items_baeda0db73')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('sales_page_id')->nullable()->constrained('content_pages', indexName: 'fk_order_items_b31f7a60fc')->restrictOnDelete()->restrictOnUpdate();
            $table->string('product_name');
            $table->string('variant_name');
            $table->string('sku');
            $table->json('options_snapshot')->nullable();
            $table->text('customization_text')->nullable();
            $table->integer('quantity');
            $table->decimal('catalog_unit_price');
            $table->decimal('applied_unit_price');
            $table->boolean('is_price_overridden');
            $table->text('price_change_reason')->nullable();
            $table->unsignedTinyInteger('price_origin')->comment('PriceOriginEnum: 1, 2, 3');
            $table->json('promotion_snapshot')->nullable();
            $table->decimal('unit_cost_snapshot');
            $table->decimal('line_total');
            $table->json('tax_snapshot');
            $table->unsignedTinyInteger('reservation_status')->nullable()->comment('StockReservationStatusEnum: 1, 2, 3');
            $table->dateTime('reserved_at')->nullable();
            $table->dateTime('reservation_released_at')->nullable();
            $table->dateTime('reservation_created_at')->nullable();
            $table->dateTime('reservation_updated_at')->nullable();
            $table->timestamp('created_at');

            $table->unique(['id', 'revision_id'], 'uq_order_items_dc5c41eb44');
            $table->unique(['id', 'variant_id'], 'uq_order_items_15acc595bd');
            $table->unique(['id', 'product_id'], 'uq_order_items_73097e04df');
            $table->index(['variant_id', 'reservation_status', 'revision_id', 'id'], 'ix_order_items_c1b518811c');
            $table->index(['revision_id', 'reservation_status', 'id'], 'ix_order_items_f75230a688');
            $table->index(['variant_id', 'product_id'], 'ix_order_items_29b92b5d93');
            $table->index(['sales_page_id', 'product_id'], 'ix_order_items_59bc361caa');
            $table->index(['product_id'], 'ix_order_items_c3adad4f81');
            $table->index(['promotion_id'], 'ix_order_items_baeda0db73');
            $table->foreign(['variant_id', 'product_id'], 'fk_order_items_29b92b5d93')->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sales_page_id', 'product_id'], 'fk_order_items_59bc361caa')->references(['id', 'product_id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
