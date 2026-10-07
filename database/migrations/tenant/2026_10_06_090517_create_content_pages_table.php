<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('product_id')->nullable();
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
            $table->dateTime('published_at', 6)->nullable();
            $table->integer('version');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
