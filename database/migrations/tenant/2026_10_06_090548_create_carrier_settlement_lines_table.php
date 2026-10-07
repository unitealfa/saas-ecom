<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_settlement_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('remittance_statement_id')->nullable();
            $table->unsignedBigInteger('collection_id')->nullable();
            $table->unsignedBigInteger('carrier_fee_id')->nullable();
            $table->unsignedBigInteger('receivable_id')->nullable();
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->unsignedBigInteger('replacement_order_id')->nullable();
            $table->unsignedBigInteger('proof_media_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unsignedBigInteger('correction_of_id')->nullable();
            $table->string('operation_key')->unique();
            $table->unsignedTinyInteger('record_type')->comment('CarrierSettlementLineTypeEnum: 1, 2, 3, 4');
            $table->decimal('amount', 14, 2);
            $table->unsignedTinyInteger('fee_payment_mode')->nullable()->comment('FeeSettlementModeEnum: 1, 2, 3, 4');
            $table->unsignedTinyInteger('receivable_settlement_type')->nullable()->comment('ReceivableSettlementTypeEnum: 1, 2, 3, 4');
            $table->string('reason')->nullable();
            $table->string('external_reference')->nullable();
            $table->dateTime('performed_at', 6)->nullable();
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_settlement_lines');
    }
};
