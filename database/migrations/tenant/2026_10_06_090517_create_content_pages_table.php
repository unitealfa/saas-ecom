<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (product_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('product_id')->nullable();
            $table->unsignedTinyInteger('page_kind')->comment('PageKindEnum: 1, 2');
            $table->string('slug');
            $table->string('type')->nullable();
            $table->string('title');
            $table->json('content');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('indexable');
            $table->boolean('is_published');
            $table->dateTime('published_at')->nullable();
            $table->integer('version');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'product_id'], 'uq_content_pages_73097e04df');
            $table->unique(['id', 'page_kind'], 'uq_content_pages_b6521fb5df');
            $table->unique(['page_kind', 'slug'], 'uq_content_pages_8da27bc49c');
            $table->index(['page_kind', 'is_published', 'deleted_at', 'published_at', 'id'], 'ix_content_pages_a80ae424cf');
            $table->index(['product_id'], 'ix_content_pages_c3adad4f81');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
