<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (carrier_fee_id, receivable_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('carrier_settlement_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('provider_id')->constrained('shipping_providers', indexName: 'fk_carrier_settlement_lines_b3c7cddea3')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('remittance_statement_id')->nullable()->constrained('remittance_statements', indexName: 'fk_carrier_settlement_lines_3637755e4b')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('collection_id')->nullable()->constrained('collections', indexName: 'fk_carrier_settlement_lines_da719fe1c4')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('carrier_fee_id')->nullable();
            $table->foreignId('receivable_id')->nullable();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments', indexName: 'fk_carrier_settlement_lines_9325aebf7e')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('replacement_order_id')->nullable()->constrained('orders', indexName: 'fk_carrier_settlement_lines_1e4156bc0b')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('proof_media_id')->nullable()->constrained('media', indexName: 'fk_carrier_settlement_lines_6aed88530f')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('reversal_of_id')->nullable()->constrained('carrier_settlement_lines', indexName: 'fk_carrier_settlement_lines_d046da8d50')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('correction_of_id')->nullable()->constrained('carrier_settlement_lines', indexName: 'fk_carrier_settlement_lines_d0f90c8322')->restrictOnDelete()->restrictOnUpdate();
            $table->string('operation_key')->unique();
            $table->unsignedTinyInteger('record_type')->comment('CarrierSettlementLineTypeEnum: 1, 2, 3, 4');
            $table->decimal('amount');
            $table->unsignedTinyInteger('fee_payment_mode')->nullable()->comment('FeeSettlementModeEnum: 1, 2, 3, 4');
            $table->unsignedTinyInteger('receivable_settlement_type')->nullable()->comment('ReceivableSettlementTypeEnum: 1, 2, 3, 4');
            $table->string('reason')->nullable();
            $table->string('external_reference')->nullable();
            $table->dateTime('performed_at')->nullable();
            $table->timestamp('created_at');

            $table->unique(['id', 'provider_id', 'record_type'], 'uq_carrier_settlement_lines_b1b8a4b1d2');
            $table->unique(['id', 'record_type', 'provider_id'], 'uq_carrier_settlement_lines_630034caf9');
            $table->unique(['id', 'record_type', 'provider_id', 'collection_id'], 'uq_carrier_settlement_lines_684ab1128a');
            $table->unique(['id', 'record_type', 'provider_id', 'carrier_fee_id'], 'uq_carrier_settlement_lines_3c35dc75d5');
            $table->unique(['id', 'record_type', 'provider_id', 'receivable_id'], 'uq_carrier_settlement_lines_51e0ae09e0');
            $table->unique(['id', 'record_type', 'provider_id', 'shipment_id'], 'uq_carrier_settlement_lines_09568d635d');
            $table->unique(['reversal_of_id'], 'uq_carrier_settlement_lines_d046da8d50');
            $table->index(['reversal_of_id', 'record_type', 'provider_id', 'collection_id'], 'ix_carrier_settlement_lines_925c950e83');
            $table->index(['reversal_of_id', 'record_type', 'provider_id', 'carrier_fee_id'], 'ix_carrier_settlement_lines_2223ed5210');
            $table->index(['reversal_of_id', 'record_type', 'provider_id', 'receivable_id'], 'ix_carrier_settlement_lines_9860c0e09b');
            $table->index(['reversal_of_id', 'record_type', 'provider_id', 'shipment_id'], 'ix_carrier_settlement_lines_857b657a79');
            $table->index(['correction_of_id', 'record_type', 'provider_id', 'collection_id'], 'ix_carrier_settlement_lines_d8429d5699');
            $table->index(['correction_of_id', 'record_type', 'provider_id', 'carrier_fee_id'], 'ix_carrier_settlement_lines_8233cbb96c');
            $table->index(['correction_of_id', 'record_type', 'provider_id', 'receivable_id'], 'ix_carrier_settlement_lines_ed98a017a8');
            $table->index(['correction_of_id', 'record_type', 'provider_id', 'shipment_id'], 'ix_carrier_settlement_lines_70de42edf6');
            $table->index(['record_type', 'receivable_id', 'performed_at'], 'ix_carrier_settlement_lines_83c607937c');
            $table->index(['carrier_fee_id', 'shipment_id', 'provider_id'], 'ix_carrier_settlement_lines_94cc5a061f');
            $table->index(['record_type', 'collection_id'], 'ix_carrier_settlement_lines_3079f88711');
            $table->index(['record_type', 'carrier_fee_id'], 'ix_carrier_settlement_lines_1770ae7898');
            $table->index(['record_type', 'remittance_statement_id'], 'ix_carrier_settlement_lines_f7f2c13bee');
            $table->index(['record_type', 'shipment_id'], 'ix_carrier_settlement_lines_96107c5138');
            $table->index(['remittance_statement_id', 'provider_id'], 'ix_carrier_settlement_lines_3988575ec8');
            $table->index(['shipment_id', 'provider_id'], 'ix_carrier_settlement_lines_c98047af1c');
            $table->index(['collection_id', 'shipment_id'], 'ix_carrier_settlement_lines_805dec760f');
            $table->index(['receivable_id', 'provider_id'], 'ix_carrier_settlement_lines_228b02ea22');
            $table->index(['provider_id'], 'ix_carrier_settlement_lines_b3c7cddea3');
            $table->index(['replacement_order_id'], 'ix_carrier_settlement_lines_1e4156bc0b');
            $table->index(['proof_media_id'], 'ix_carrier_settlement_lines_6aed88530f');
            $table->foreign(['remittance_statement_id', 'provider_id'], 'fk_carrier_settlement_lines_3988575ec8')->references(['id', 'provider_id'])->on('remittance_statements')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipment_id', 'provider_id'], 'fk_carrier_settlement_lines_c98047af1c')->references(['id', 'provider_id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['collection_id', 'shipment_id'], 'fk_carrier_settlement_lines_805dec760f')->references(['id', 'shipment_id'])->on('collections')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'record_type', 'provider_id'], 'fk_carrier_settlement_lines_4570412ab1')->references(['id', 'record_type', 'provider_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'record_type', 'provider_id', 'collection_id'], 'fk_carrier_settlement_lines_925c950e83')->references(['id', 'record_type', 'provider_id', 'collection_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'record_type', 'provider_id', 'carrier_fee_id'], 'fk_carrier_settlement_lines_2223ed5210')->references(['id', 'record_type', 'provider_id', 'carrier_fee_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'record_type', 'provider_id', 'receivable_id'], 'fk_carrier_settlement_lines_9860c0e09b')->references(['id', 'record_type', 'provider_id', 'receivable_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'record_type', 'provider_id', 'shipment_id'], 'fk_carrier_settlement_lines_857b657a79')->references(['id', 'record_type', 'provider_id', 'shipment_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'record_type', 'provider_id'], 'fk_carrier_settlement_lines_0c47191ede')->references(['id', 'record_type', 'provider_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'record_type', 'provider_id', 'collection_id'], 'fk_carrier_settlement_lines_d8429d5699')->references(['id', 'record_type', 'provider_id', 'collection_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'record_type', 'provider_id', 'carrier_fee_id'], 'fk_carrier_settlement_lines_8233cbb96c')->references(['id', 'record_type', 'provider_id', 'carrier_fee_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'record_type', 'provider_id', 'receivable_id'], 'fk_carrier_settlement_lines_ed98a017a8')->references(['id', 'record_type', 'provider_id', 'receivable_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'record_type', 'provider_id', 'shipment_id'], 'fk_carrier_settlement_lines_70de42edf6')->references(['id', 'record_type', 'provider_id', 'shipment_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_settlement_lines');
    }
};
