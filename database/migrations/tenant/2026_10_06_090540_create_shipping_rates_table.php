<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->unsignedBigInteger('carrier_account_id')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->char('province_uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable();
            $table->char('municipality_uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable();
            $table->unsignedTinyInteger('record_type')->comment('ShippingRateRecordTypeEnum: 1, 2, 3');
            $table->unsignedTinyInteger('delivery_mode')->nullable()->comment('DeliveryModeEnum: 1, 2');
            $table->unsignedTinyInteger('service_type')->nullable()->comment('ServiceTypeEnum: 1, 2');
            $table->decimal('amount', 14, 2);
            $table->unsignedTinyInteger('source')->nullable()->comment('ProviderRateSourceEnum: 1, 2');
            $table->dateTime('retrieved_at', 6)->nullable();
            $table->dateTime('starts_at', 6)->nullable();
            $table->dateTime('ends_at', 6)->nullable();
            $table->boolean('is_active');
            $table->unsignedBigInteger('provider_scope_id')->nullable(false)->storedAs('COALESCE(provider_id, 0)');
            $table->char('municipality_scope_uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable()->storedAs('COALESCE(municipality_uuid, province_uuid)');
            $table->unsignedTinyInteger('current_slot')->nullable()->storedAs('CASE WHEN record_type = 1 AND is_active = 1 AND deleted_at IS NULL THEN 1 WHEN record_type = 2 AND deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6)->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
