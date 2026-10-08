<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('cart_id')->constrained('carts', indexName: 'fk_cart_items_eb4226caa5')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('variant_id')->constrained('product_variants', indexName: 'fk_cart_items_14f215ed6d')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_id')->constrained('products', indexName: 'fk_cart_items_c3adad4f81')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('sales_page_id')->nullable()->constrained('content_pages', indexName: 'fk_cart_items_b31f7a60fc')->restrictOnDelete()->restrictOnUpdate();
            $table->integer('quantity');
            $table->text('customization_text')->nullable();
            $table->char('customization_signature')->charset('ascii')->collation('ascii_bin');
            $table->timestamps();

            $table->index(['variant_id', 'product_id'], 'ix_cart_items_29b92b5d93');
            $table->index(['sales_page_id', 'product_id'], 'ix_cart_items_59bc361caa');
            $table->index(['cart_id'], 'ix_cart_items_eb4226caa5');
            $table->index(['product_id'], 'ix_cart_items_c3adad4f81');
            $table->foreign(['variant_id', 'product_id'], 'fk_cart_items_29b92b5d93')->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sales_page_id', 'product_id'], 'fk_cart_items_59bc361caa')->references(['id', 'product_id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
