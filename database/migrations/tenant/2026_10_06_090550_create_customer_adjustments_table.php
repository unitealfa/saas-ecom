<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (incident_id, credit_note_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('customer_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('order_id')->constrained('orders', indexName: 'fk_customer_adjustments_ca13a6b2c9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('return_id')->nullable()->constrained('order_returns', indexName: 'fk_customer_adjustments_4ee7679ca5')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('incident_id');
            $table->foreignId('credit_note_id')->nullable();
            $table->foreignId('validated_by_id')->nullable()->constrained('users', indexName: 'fk_customer_adjustments_6a16de7f77')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('proof_media_id')->nullable()->constrained('media', indexName: 'fk_customer_adjustments_6aed88530f')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('reversal_of_id')->nullable()->constrained('customer_adjustments', indexName: 'fk_customer_adjustments_d046da8d50')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('correction_of_id')->nullable()->constrained('customer_adjustments', indexName: 'fk_customer_adjustments_d0f90c8322')->restrictOnDelete()->restrictOnUpdate();
            $table->integer('compensated_quantity');
            $table->unsignedTinyInteger('amount_kind')->comment('AmountKindEnum: 1, 2, 3');
            $table->unsignedTinyInteger('type')->comment('AdjustmentTypeEnum: 1, 2, 3');
            $table->decimal('amount');
            $table->unsignedTinyInteger('status')->default(1)->comment('AdjustmentStatusEnum: 1, 2, 3, 4, 5');
            $table->dateTime('performed_at')->nullable();
            $table->string('reference')->nullable();
            $table->text('reason');
            $table->string('operation_key');
            $table->timestamps();

            $table->unique(['id', 'order_id', 'incident_id'], 'uq_customer_adjustments_10f264567a');
            $table->unique(['operation_key'], 'uq_customer_adjustments_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_customer_adjustments_d046da8d50');
            $table->index(['reversal_of_id', 'order_id', 'incident_id'], 'ix_customer_adjustments_1220d124fe');
            $table->index(['correction_of_id', 'order_id', 'incident_id'], 'ix_customer_adjustments_1ebd6bb067');
            $table->index(['return_id', 'status'], 'ix_customer_adjustments_e25618ae97');
            $table->index(['incident_id', 'status'], 'ix_customer_adjustments_dc6588423e');
            $table->index(['incident_id', 'order_id'], 'ix_customer_adjustments_4e2d7fc88d');
            $table->index(['order_id'], 'ix_customer_adjustments_ca13a6b2c9');
            $table->index(['credit_note_id'], 'ix_customer_adjustments_d61185a1fb');
            $table->index(['validated_by_id'], 'ix_customer_adjustments_6a16de7f77');
            $table->index(['proof_media_id'], 'ix_customer_adjustments_6aed88530f');
            $table->foreign(['reversal_of_id', 'order_id', 'incident_id'], 'fk_customer_adjustments_1220d124fe')->references(['id', 'order_id', 'incident_id'])->on('customer_adjustments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'order_id', 'incident_id'], 'fk_customer_adjustments_1ebd6bb067')->references(['id', 'order_id', 'incident_id'])->on('customer_adjustments')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_adjustments');
    }
};
