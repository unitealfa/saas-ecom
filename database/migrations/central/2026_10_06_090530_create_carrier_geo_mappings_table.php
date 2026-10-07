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
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('carrier_id');
            $table->unsignedBigInteger('geographic_area_id');
            $table->unsignedTinyInteger('zone_type')->comment('GeoZoneTypeEnum: 1, 2');
            $table->string('external_code');
            $table->string('external_name');
            $table->string('external_province_code');
            $table->string('verification_source');
            $table->dateTime('verified_at', 6)->nullable();
            $table->integer('mapping_version');
            $table->boolean('is_active');
            $table->dateTime('synced_at', 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_geo_mappings');
    }
};
