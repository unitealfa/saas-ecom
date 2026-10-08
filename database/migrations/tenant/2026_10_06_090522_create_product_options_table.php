<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('product_id')->constrained('products', indexName: 'fk_product_options_c3adad4f81')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('parent_id')->nullable()->constrained('product_options', indexName: 'fk_product_options_7b54484fae')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('record_type')->comment('ProductOptionRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('parent_record_type')->nullable()->storedAs('CASE WHEN parent_id IS NOT NULL THEN 1 ELSE NULL END')->comment('ProductOptionRecordTypeEnum: 1, 2');
            $table->string('name')->collation('utf8mb4_0900_ai_ci');
            $table->string('identity_code')->nullable();
            $table->unsignedTinyInteger('display_type')->nullable()->comment('OptionDisplayTypeEnum: 1, 2, 3');
            $table->char('color_hex')->nullable();
            $table->integer('position');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'product_id', 'record_type'], 'uq_product_options_71161dc6f3');
            $table->unique(['id', 'parent_id', 'product_id', 'record_type'], 'uq_product_options_28eb9ab60d');
            $table->unique(['parent_id', 'identity_code'], 'uq_product_options_66c74ef23d');
            $table->index(['product_id', 'record_type', 'deleted_at', 'position', 'id'], 'ix_product_options_93c4b10c5e');
            $table->index(['parent_id', 'product_id', 'parent_record_type'], 'ix_product_options_12a5993334');
            $table->foreign(['parent_id', 'product_id', 'parent_record_type'], 'fk_product_options_12a5993334')->references(['id', 'product_id', 'record_type'])->on('product_options')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
