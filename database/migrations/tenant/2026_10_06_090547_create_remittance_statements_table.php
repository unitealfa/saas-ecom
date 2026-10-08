<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (carrier_remittance_batch_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('remittance_statements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('provider_id')->constrained('shipping_providers', indexName: 'fk_remittance_statements_b3c7cddea3')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('carrier_remittance_batch_id')->nullable();
            $table->foreignId('validated_by_id')->nullable()->constrained('users', indexName: 'fk_remittance_statements_6a16de7f77')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('proof_media_id')->nullable()->constrained('media', indexName: 'fk_remittance_statements_6aed88530f')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('reversal_of_id')->nullable()->constrained('remittance_statements', indexName: 'fk_remittance_statements_d046da8d50')->restrictOnDelete()->restrictOnUpdate();
            $table->string('number');
            $table->string('external_reference')->nullable();
            $table->unsignedTinyInteger('type')->comment('StatementTypeEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('status')->default(1)->comment('RemittanceStatementStatusEnum: 1, 2, 3, 4, 5, 6');
            $table->decimal('gross_amount');
            $table->decimal('fee_amount');
            $table->decimal('expected_net_amount');
            $table->decimal('received_net_amount')->nullable();
            $table->dateTime('declared_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->text('note')->nullable();
            $table->string('operation_key');
            $table->dateTime('reconciled_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'provider_id'], 'uq_remittance_statements_b8600d5a3c');
            $table->unique(['provider_id', 'number'], 'uq_remittance_statements_fcda4f1488');
            $table->unique(['operation_key'], 'uq_remittance_statements_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_remittance_statements_d046da8d50');
            $table->index(['carrier_remittance_batch_id', 'status'], 'ix_remittance_statements_19f845dd4a');
            $table->index(['validated_by_id'], 'ix_remittance_statements_6a16de7f77');
            $table->index(['proof_media_id'], 'ix_remittance_statements_6aed88530f');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_statements');
    }
};
