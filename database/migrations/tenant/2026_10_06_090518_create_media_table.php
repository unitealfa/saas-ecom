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
            $table->uuid('uuid');
            $table->foreignId('created_by_id')->nullable()->constrained('users', indexName: 'fk_media_6fb667974b')->restrictOnDelete()->restrictOnUpdate();
            $table->string('storage_key')->unique();
            $table->unsignedBigInteger('model_id');
            $table->string('model_type');
            $table->string('collection_name');
            $table->string('disk');
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
            $table->unsignedTinyInteger('primary_slot')->nullable()->storedAs('CASE WHEN is_primary = TRUE AND deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->char('file_hash')->charset('ascii')->collation('ascii_bin')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['model_type', 'model_id', 'collection_name', 'primary_slot'], 'uq_media_ba36efe3ba');
            $table->index(['created_by_id'], 'ix_media_6fb667974b');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
