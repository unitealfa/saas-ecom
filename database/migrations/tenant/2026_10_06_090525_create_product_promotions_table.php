<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_promotions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('product_id')->constrained('products', indexName: 'fk_product_promotions_c3adad4f81')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants', indexName: 'fk_product_promotions_14f215ed6d')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('sales_page_id')->nullable()->constrained('content_pages', indexName: 'fk_product_promotions_b31f7a60fc')->restrictOnDelete()->restrictOnUpdate();
            $table->string('name');
            $table->unsignedTinyInteger('discount_type')->comment('DiscountTypeEnum: 1, 2, 3');
            $table->decimal('value');
            $table->integer('minimum_quantity');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->integer('priority');
            $table->boolean('is_active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['variant_id', 'product_id'], 'ix_product_promotions_29b92b5d93');
            $table->index(['sales_page_id', 'product_id'], 'ix_product_promotions_59bc361caa');
            $table->index(['product_id'], 'ix_product_promotions_c3adad4f81');
            $table->foreign(['variant_id', 'product_id'], 'fk_product_promotions_29b92b5d93')->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sales_page_id', 'product_id'], 'fk_product_promotions_59bc361caa')->references(['id', 'product_id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_promotions');
    }
};
