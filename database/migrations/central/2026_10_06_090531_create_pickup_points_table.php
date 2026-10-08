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
            $table->uuid('uuid');
            $table->foreignId('carrier_id')->constrained('shipping_carriers', indexName: 'fk_pickup_points_e3643dfebd')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('province_id')->constrained('geographic_areas', indexName: 'fk_pickup_points_dd6587e1f7')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('municipality_id')->nullable()->constrained('geographic_areas', indexName: 'fk_pickup_points_4b1b598381')->restrictOnDelete()->restrictOnUpdate();
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
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['carrier_id', 'external_code'], 'uq_pickup_points_c5a57a212b');
            $table->index(['municipality_id', 'province_id', 'municipality_type'], 'ix_pickup_points_43469833ce');
            $table->index(['province_id', 'province_type'], 'ix_pickup_points_1c4b789574');
            $table->foreign(['province_id', 'province_type'], 'fk_pickup_points_1c4b789574')->references(['id', 'type'])->on('geographic_areas')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['municipality_id', 'province_id', 'municipality_type'], 'fk_pickup_points_43469833ce')->references(['id', 'parent_id', 'type'])->on('geographic_areas')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_points');
    }
};
