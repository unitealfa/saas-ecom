<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('product_id')->constrained('products', indexName: 'fk_product_variants_c3adad4f81')->restrictOnDelete()->restrictOnUpdate();
            $table->string('label');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->char('combination_signature')->charset('ascii')->collation('ascii_bin');
            $table->dateTime('used_at')->nullable();
            $table->decimal('sale_price');
            $table->decimal('unit_cost');
            $table->decimal('previous_price')->nullable();
            $table->json('tax_configuration')->nullable();
            $table->integer('physical_stock');
            $table->integer('reserved_stock');
            $table->integer('quarantine_stock');
            $table->integer('low_stock_threshold');
            $table->decimal('weight_kg')->nullable();
            $table->decimal('length_cm')->nullable();
            $table->decimal('width_cm')->nullable();
            $table->decimal('height_cm')->nullable();
            $table->boolean('is_active');
            $table->integer('position');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'product_id'], 'uq_product_variants_73097e04df');
            $table->unique(['sku'], 'uq_product_variants_bc7b047a69');
            $table->unique(['product_id', 'combination_signature'], 'uq_product_variants_1d03bffc80');
            $table->index(['product_id', 'is_active'], 'ix_product_variants_c3540f7294');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
