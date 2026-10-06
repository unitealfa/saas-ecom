<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('shipped_revision_id');
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('label_media_id')->nullable();
            $table->unsignedBigInteger('assigned_by_id');
            $table->char('pickup_point_uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable();
            $table->unsignedTinyInteger('delivery_mode')->comment('DeliveryModeEnum: 1, 2');
            $table->unsignedTinyInteger('status')->default(1)->comment('ShipmentStatusEnum: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11');
            $table->string('raw_external_status')->nullable();
            $table->string('tracking')->nullable();
            $table->string('merchant_reference')->nullable();
            $table->string('external_reference')->nullable();
            $table->decimal('cod_amount', 14, 2);
            $table->decimal('estimated_cost', 14, 2);
            $table->decimal('weight_kg', 14, 3)->nullable();
            $table->boolean('is_fragile');
            $table->dateTime('shipped_at', 6)->nullable();
            $table->dateTime('carrier_validated_at', 6)->nullable();
            $table->dateTime('delivered_at', 6)->nullable();
            $table->dateTime('last_synced_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
