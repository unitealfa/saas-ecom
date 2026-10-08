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
        Schema::create('carrier_fees', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('shipment_id')->constrained('shipments', indexName: 'fk_carrier_fees_9325aebf7e')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('provider_id')->constrained('shipping_providers', indexName: 'fk_carrier_fees_b3c7cddea3')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('return_id')->nullable()->constrained('order_returns', indexName: 'fk_carrier_fees_4ee7679ca5')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('carrier_account_id')->nullable();
            $table->foreignId('source_rate_id')->nullable()->constrained('shipping_rates', indexName: 'fk_carrier_fees_8c8e195cf9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('proof_media_id')->nullable()->constrained('media', indexName: 'fk_carrier_fees_6aed88530f')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('reversal_of_id')->nullable()->constrained('carrier_fees', indexName: 'fk_carrier_fees_d046da8d50')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('correction_of_id')->nullable()->constrained('carrier_fees', indexName: 'fk_carrier_fees_d0f90c8322')->restrictOnDelete()->restrictOnUpdate();
            $table->json('rate_snapshot')->nullable();
            $table->unsignedTinyInteger('source_rate_record_type')->nullable()->storedAs('CASE WHEN source_rate_id IS NOT NULL THEN 3 ELSE NULL END')->comment('ShippingRateRecordTypeEnum: 1, 2, 3');
            $table->unsignedTinyInteger('fee_type')->comment('CarrierFeeTypeEnum: 1, 2, 3, 4, 5, 6');
            $table->unsignedTinyInteger('payer')->comment('FeePayerEnum: 1, 2, 3, 4');
            $table->unsignedTinyInteger('settlement_mode')->comment('FeeSettlementModeEnum: 1, 2, 3, 4');
            $table->decimal('amount');
            $table->unsignedTinyInteger('status')->default(1)->comment('CarrierFeeStatusEnum: 1, 2, 3, 4, 5');
            $table->dateTime('triggered_at');
            $table->string('date_source');
            $table->dateTime('recognized_at')->nullable();
            $table->string('external_reference')->nullable();
            $table->string('operation_key');
            $table->timestamps();

            $table->unique(['id', 'shipment_id', 'provider_id'], 'uq_carrier_fees_71fa8c67a5');
            $table->unique(['operation_key'], 'uq_carrier_fees_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_carrier_fees_d046da8d50');
            $table->index(['source_rate_id', 'carrier_account_id', 'source_rate_record_type'], 'ix_carrier_fees_3d038b8aa4');
            $table->index(['shipment_id', 'status'], 'ix_carrier_fees_002c46552c');
            $table->index(['shipment_id', 'provider_id'], 'ix_carrier_fees_c98047af1c');
            $table->index(['return_id', 'shipment_id'], 'ix_carrier_fees_a663f454eb');
            $table->index(['provider_id'], 'ix_carrier_fees_b3c7cddea3');
            $table->index(['carrier_account_id'], 'ix_carrier_fees_fd68d11272');
            $table->index(['proof_media_id'], 'ix_carrier_fees_6aed88530f');
            $table->index(['correction_of_id'], 'ix_carrier_fees_d0f90c8322');
            $table->foreign(['source_rate_id', 'carrier_account_id', 'source_rate_record_type'], 'fk_carrier_fees_3d038b8aa4')->references(['id', 'carrier_account_id', 'record_type'])->on('shipping_rates')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipment_id', 'provider_id'], 'fk_carrier_fees_c98047af1c')->references(['id', 'provider_id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['return_id', 'shipment_id'], 'fk_carrier_fees_a663f454eb')->references(['id', 'shipment_id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_fees');
    }
};
