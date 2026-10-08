<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_tags', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('product_id')->constrained('products', indexName: 'fk_product_tags_c3adad4f81')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('tag_id')->constrained('categories', indexName: 'fk_product_tags_6040e81886')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('tag_record_type')->nullable(false)->storedAs('2');
            $table->timestamps();

            $table->unique(['product_id', 'tag_id'], 'uq_product_tags_64a1fb9477');
            $table->index(['tag_id', 'tag_record_type'], 'ix_product_tags_e950e25458');
            $table->foreign(['tag_id', 'tag_record_type'], 'fk_product_tags_e950e25458')->references(['id', 'record_type'])->on('categories')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tags');
    }
};
