<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (carrier_account_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('provider_id')->nullable()->constrained('shipping_providers', indexName: 'fk_shipping_rates_b3c7cddea3')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('carrier_account_id')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users', indexName: 'fk_shipping_rates_6fb667974b')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('province_uuid')->nullable();
            $table->uuid('municipality_uuid')->nullable();
            $table->unsignedTinyInteger('record_type')->comment('ShippingRateRecordTypeEnum: 1, 2, 3');
            $table->unsignedTinyInteger('delivery_mode')->nullable()->comment('DeliveryModeEnum: 1, 2');
            $table->unsignedTinyInteger('service_type')->nullable()->comment('ServiceTypeEnum: 1, 2');
            $table->decimal('amount');
            $table->unsignedTinyInteger('source')->nullable()->comment('ProviderRateSourceEnum: 1, 2');
            $table->dateTime('retrieved_at')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active');
            $table->unsignedBigInteger('provider_scope_id')->nullable(false)->storedAs('COALESCE(provider_id, 0)');
            $table->uuid('municipality_scope_uuid')->nullable()->storedAs('COALESCE(municipality_uuid, province_uuid)');
            $table->unsignedTinyInteger('current_slot')->nullable()->storedAs('CASE WHEN record_type = 1 AND is_active = TRUE AND deleted_at IS NULL THEN 1 WHEN record_type = 2 AND deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'carrier_account_id', 'record_type'], 'uq_shipping_rates_a8ca41906b');
            $table->unique(['record_type', 'carrier_account_id', 'starts_at'], 'uq_shipping_rates_4542e653d8');
            $table->unique(['record_type', 'provider_scope_id', 'province_uuid', 'municipality_scope_uuid', 'delivery_mode', 'service_type', 'current_slot'], 'uq_shipping_rates_6c2746eef2');
            $table->index(['record_type', 'carrier_account_id', 'is_active', 'starts_at'], 'ix_shipping_rates_5343324d31');
            $table->index(['provider_id'], 'ix_shipping_rates_b3c7cddea3');
            $table->index(['carrier_account_id'], 'ix_shipping_rates_fd68d11272');
            $table->index(['created_by_id'], 'ix_shipping_rates_6fb667974b');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
