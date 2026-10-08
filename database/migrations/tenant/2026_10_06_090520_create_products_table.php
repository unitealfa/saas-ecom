<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('category_id')->nullable()->constrained('categories', indexName: 'fk_products_4f0d62547a')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('category_record_type')->nullable()->storedAs('CASE WHEN category_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->string('name');
            $table->string('slug');
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->json('benefits')->nullable();
            $table->json('faq')->nullable();
            $table->string('brand')->nullable();
            $table->unsignedTinyInteger('type')->comment('ProductTypeEnum: 1, 2');
            $table->boolean('allows_customization');
            $table->text('customization_instructions')->nullable();
            $table->string('sale_unit');
            $table->decimal('content_quantity', places: 3)->nullable();
            $table->string('content_unit')->nullable();
            $table->unsignedTinyInteger('status')->default(1)->comment('PublicationStatusEnum: 1, 2, 3');
            $table->dateTime('published_at')->nullable();
            $table->boolean('is_featured');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('indexable');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug'], 'uq_products_cd03861f0f');
            $table->index(['category_id', 'category_record_type'], 'ix_products_0846ff10b7');
            $table->foreign(['category_id', 'category_record_type'], 'fk_products_0846ff10b7')->references(['id', 'record_type'])->on('categories')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
