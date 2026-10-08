<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geographic_areas', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('country_id')->constrained('countries', indexName: 'fk_geographic_areas_1bd5d05f10')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('parent_id')->nullable()->constrained('geographic_areas', indexName: 'fk_geographic_areas_7b54484fae')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('type')->comment('GeoZoneTypeEnum: 1, 2');
            $table->unsignedTinyInteger('parent_type')->nullable()->storedAs('CASE WHEN type = 2 THEN 1 ELSE NULL END');
            $table->unsignedBigInteger('parent_key')->nullable(false)->storedAs('COALESCE(parent_id, 0)');
            $table->string('code');
            $table->string('name_fr');
            $table->string('name_ar')->nullable();
            $table->boolean('is_active');
            $table->string('reference_source');
            $table->date('effective_at');
            $table->string('reference_version');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'country_id', 'type'], 'uq_geographic_areas_a1f59ca9b7');
            $table->unique(['id', 'type'], 'uq_geographic_areas_a31b24084c');
            $table->unique(['id', 'parent_id', 'type'], 'uq_geographic_areas_c2b88c4bed');
            $table->unique(['country_id', 'type', 'parent_key', 'code'], 'uq_geographic_areas_83fb06bf15');
            $table->index(['country_id', 'type', 'parent_id', 'is_active'], 'ix_geographic_areas_3182351b15');
            $table->index(['parent_id', 'country_id', 'parent_type'], 'ix_geographic_areas_8a88bda689');
            $table->foreign(['parent_id', 'country_id', 'parent_type'], 'fk_geographic_areas_8a88bda689')->references(['id', 'country_id', 'type'])->on('geographic_areas')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geographic_areas');
    }
};
