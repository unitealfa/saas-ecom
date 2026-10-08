<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_geo_mappings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('carrier_id')->constrained('shipping_carriers', indexName: 'fk_carrier_geo_mappings_e3643dfebd')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('geographic_area_id')->constrained('geographic_areas', indexName: 'fk_carrier_geo_mappings_89f600e82e')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('zone_type')->comment('GeoZoneTypeEnum: 1, 2');
            $table->string('external_code');
            $table->string('external_name');
            $table->string('external_province_code');
            $table->string('verification_source');
            $table->dateTime('verified_at')->nullable();
            $table->integer('mapping_version');
            $table->boolean('is_active');
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['carrier_id', 'geographic_area_id'], 'uq_carrier_geo_mappings_5e037a4a2a');
            $table->index(['geographic_area_id', 'zone_type'], 'ix_carrier_geo_mappings_75c5c7008c');
            $table->foreign(['geographic_area_id', 'zone_type'], 'fk_carrier_geo_mappings_75c5c7008c')->references(['id', 'type'])->on('geographic_areas')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_geo_mappings');
    }
};
