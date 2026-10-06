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
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('category_id')->nullable();
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
            $table->decimal('content_quantity', 14, 3)->nullable();
            $table->string('content_unit')->nullable();
            $table->unsignedTinyInteger('status')->default(1)->comment('PublicationStatusEnum: 1, 2, 3');
            $table->dateTime('published_at', 6)->nullable();
            $table->boolean('is_featured');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('indexable');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
