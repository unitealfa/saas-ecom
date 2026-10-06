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
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('country_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedTinyInteger('type')->comment('GeoZoneTypeEnum: 1, 2');
            $table->unsignedTinyInteger('parent_type')->nullable()->storedAs('CASE WHEN type = 2 THEN 1 ELSE NULL END');
            $table->unsignedBigInteger('parent_key')->nullable(false)->storedAs('COALESCE(parent_id, 0)');
            $table->string('code', 32);
            $table->string('name_fr');
            $table->string('name_ar')->nullable();
            $table->boolean('is_active');
            $table->string('reference_source');
            $table->date('effective_at');
            $table->string('reference_version');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geographic_areas');
    }
};
