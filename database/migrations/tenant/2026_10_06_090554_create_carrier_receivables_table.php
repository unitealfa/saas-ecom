<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_receivables', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('carrier_fee_id');
            $table->unsignedBigInteger('original_fee_payment_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->decimal('initial_amount', 14, 2);
            $table->decimal('remaining_amount', 14, 2);
            $table->string('reason');
            $table->unsignedTinyInteger('original_fee_payment_record_type')->nullable()->storedAs('CASE WHEN original_fee_payment_id IS NOT NULL THEN 2 ELSE NULL END')->comment('CarrierSettlementLineTypeEnum: 1, 2, 3, 4');
            $table->unsignedTinyInteger('status')->default(1)->comment('ReceivableStatusEnum: 1, 2, 3, 4, 5');
            $table->string('operation_key');
            $table->dateTime('recognized_at', 6);
            $table->dateTime('settled_at', 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_receivables');
    }
};
