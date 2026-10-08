<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_option_values', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('product_id')->constrained('products', indexName: 'fk_variant_option_values_c3adad4f81')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('variant_id')->constrained('product_variants', indexName: 'fk_variant_option_values_14f215ed6d')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('option_id')->constrained('product_options', indexName: 'fk_variant_option_values_5fb9f6d52f')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('value_id')->constrained('product_options', indexName: 'fk_variant_option_values_9714733197')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('option_record_type')->nullable(false)->storedAs('1')->comment('ProductOptionRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('value_record_type')->nullable(false)->storedAs('2')->comment('ProductOptionRecordTypeEnum: 1, 2');
            $table->timestamps();

            $table->unique(['variant_id', 'option_id'], 'uq_variant_option_values_6b3172664d');
            $table->index(['value_id', 'option_id', 'product_id', 'value_record_type'], 'ix_variant_option_values_ef33e1fc9b');
            $table->index(['option_id', 'product_id', 'option_record_type'], 'ix_variant_option_values_537c9500da');
            $table->index(['variant_id', 'product_id'], 'ix_variant_option_values_29b92b5d93');
            $table->index(['product_id'], 'ix_variant_option_values_c3adad4f81');
            $table->foreign(['variant_id', 'product_id'], 'fk_variant_option_values_29b92b5d93')->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['option_id', 'product_id', 'option_record_type'], 'fk_variant_option_values_537c9500da')->references(['id', 'product_id', 'record_type'])->on('product_options')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['value_id', 'option_id', 'product_id', 'value_record_type'], 'fk_variant_option_values_ef33e1fc9b')->references(['id', 'parent_id', 'product_id', 'record_type'])->on('product_options')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_option_values');
    }
};
