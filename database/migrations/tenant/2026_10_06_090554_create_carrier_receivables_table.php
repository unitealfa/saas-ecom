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
            $table->uuid('uuid');
            $table->foreignId('provider_id')->constrained('shipping_providers', indexName: 'fk_carrier_receivables_b3c7cddea3')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('carrier_fee_id')->constrained('carrier_fees', indexName: 'fk_carrier_receivables_15d08bb160')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('original_fee_payment_id')->nullable()->constrained('carrier_settlement_lines', indexName: 'fk_carrier_receivables_2508791d40')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('reversal_of_id')->nullable()->constrained('carrier_receivables', indexName: 'fk_carrier_receivables_d046da8d50')->restrictOnDelete()->restrictOnUpdate();
            $table->decimal('initial_amount');
            $table->decimal('remaining_amount');
            $table->string('reason');
            $table->unsignedTinyInteger('original_fee_payment_record_type')->nullable()->storedAs('CASE WHEN original_fee_payment_id IS NOT NULL THEN 2 ELSE NULL END')->comment('CarrierSettlementLineTypeEnum: 1, 2, 3, 4');
            $table->unsignedTinyInteger('status')->default(1)->comment('ReceivableStatusEnum: 1, 2, 3, 4, 5');
            $table->string('operation_key');
            $table->dateTime('recognized_at');
            $table->dateTime('settled_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'provider_id'], 'uq_carrier_receivables_b8600d5a3c');
            $table->unique(['operation_key'], 'uq_carrier_receivables_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_carrier_receivables_d046da8d50');
            $table->index(['provider_id', 'status', 'remaining_amount'], 'ix_carrier_receivables_aeac33c348');
            $table->index(['original_fee_payment_id', 'provider_id', 'original_fee_payment_record_type'], 'ix_carrier_receivables_c187825194');
            $table->index(['reversal_of_id', 'provider_id'], 'ix_carrier_receivables_82e929ff12');
            $table->index(['carrier_fee_id'], 'ix_carrier_receivables_15d08bb160');
            $table->foreign(['original_fee_payment_id', 'provider_id', 'original_fee_payment_record_type'], 'fk_carrier_receivables_c187825194')->references(['id', 'provider_id', 'record_type'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'provider_id'], 'fk_carrier_receivables_82e929ff12')->references(['id', 'provider_id'])->on('carrier_receivables')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_receivables');
    }
};
