<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_points', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('carrier_id');
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->unsignedTinyInteger('province_type')->nullable(false)->storedAs('1');
            $table->unsignedTinyInteger('municipality_type')->nullable()->storedAs('CASE WHEN municipality_id IS NOT NULL THEN 2 ELSE NULL END');
            $table->string('external_code');
            $table->string('name');
            $table->text('address');
            $table->string('phone')->nullable();
            $table->string('map_url')->nullable();
            $table->boolean('is_carrier_active');
            $table->string('reference_source');
            $table->integer('reference_version');
            $table->dateTime('synced_at', 6)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_points');
    }
};
