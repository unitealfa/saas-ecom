<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_fees', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('return_id')->nullable();
            $table->unsignedBigInteger('carrier_account_id')->nullable();
            $table->unsignedBigInteger('source_rate_id')->nullable();
            $table->unsignedBigInteger('proof_media_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unsignedBigInteger('correction_of_id')->nullable();
            $table->json('rate_snapshot')->nullable();
            $table->unsignedTinyInteger('source_rate_record_type')->nullable()->storedAs('CASE WHEN source_rate_id IS NOT NULL THEN 3 ELSE NULL END')->comment('ShippingRateRecordTypeEnum: 1, 2, 3');
            $table->unsignedTinyInteger('fee_type')->comment('CarrierFeeTypeEnum: 1, 2, 3, 4, 5, 6');
            $table->unsignedTinyInteger('payer')->comment('FeePayerEnum: 1, 2, 3, 4');
            $table->unsignedTinyInteger('settlement_mode')->comment('FeeSettlementModeEnum: 1, 2, 3, 4');
            $table->decimal('amount', 14, 2);
            $table->unsignedTinyInteger('status')->default(1)->comment('CarrierFeeStatusEnum: 1, 2, 3, 4, 5');
            $table->dateTime('triggered_at', 6);
            $table->string('date_source');
            $table->dateTime('recognized_at', 6)->nullable();
            $table->string('external_reference')->nullable();
            $table->string('operation_key');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_fees');
    }
};
