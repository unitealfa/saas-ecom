<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('parent_id')->nullable()->constrained('categories', indexName: 'fk_categories_7b54484fae')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('media_id')->nullable()->constrained('media', indexName: 'fk_categories_d3fc3e3e3a')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('record_type')->comment('CategoryRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('parent_record_type')->nullable()->storedAs('CASE WHEN parent_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('position');
            $table->boolean('is_active');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'record_type'], 'uq_categories_8e50b89e8a');
            $table->unique(['record_type', 'slug'], 'uq_categories_f285a07437');
            $table->index(['parent_id', 'parent_record_type'], 'ix_categories_0f3b800172');
            $table->index(['media_id'], 'ix_categories_d3fc3e3e3a');
            $table->foreign(['parent_id', 'parent_record_type'], 'fk_categories_0f3b800172')->references(['id', 'record_type'])->on('categories')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
