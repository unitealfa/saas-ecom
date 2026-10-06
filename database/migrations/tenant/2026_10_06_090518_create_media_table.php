<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('storage_key')->unique();
            $table->unsignedBigInteger('model_id');
            $table->string('model_type', 64);
            $table->string('collection_name', 64);
            $table->string('disk', 64);
            $table->string('mime_type');
            $table->string('original_name');
            $table->unsignedBigInteger('size_bytes');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->text('alt_text')->nullable();
            $table->unsignedTinyInteger('visibility')->comment('MediaVisibilityEnum: 1, 2');
            $table->integer('position');
            $table->boolean('is_primary');
            $table->unsignedTinyInteger('primary_slot')->nullable()->storedAs('CASE WHEN is_primary = 1 AND deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->char('file_hash', 64)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
