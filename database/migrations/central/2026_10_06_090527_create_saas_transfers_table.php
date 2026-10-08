<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (proof_media_id, source_proof_media_id) are constrained by 2026_10_06_091357_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('saas_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id')->constrained('users', indexName: 'fk_saas_transfers_f89d6b6960')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('document_id')->constrained('saas_invoices', indexName: 'fk_saas_transfers_2a8e659395')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('original_payment_id')->nullable()->constrained('saas_transfers', indexName: 'fk_saas_transfers_f1937ef24c')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('credit_note_id')->nullable()->constrained('saas_invoices', indexName: 'fk_saas_transfers_d61185a1fb')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('proof_media_id')->nullable();
            $table->foreignId('source_proof_media_id')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users', indexName: 'fk_saas_transfers_6fb667974b')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('validated_by_id')->nullable()->constrained('users', indexName: 'fk_saas_transfers_6a16de7f77')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('performed_by_id')->nullable()->constrained('users', indexName: 'fk_saas_transfers_c71a2bb3e9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('reversal_of_id')->nullable()->constrained('saas_transfers', indexName: 'fk_saas_transfers_d046da8d50')->restrictOnDelete()->restrictOnUpdate();
            $table->char('active_transaction_fingerprint')->charset('ascii')->collation('ascii_bin')->nullable()->storedAs('CASE WHEN transfer_status = 3 AND reversal_of_id IS NULL THEN transaction_fingerprint ELSE NULL END')->unique();
            $table->string('operation_key')->unique();
            $table->unsignedTinyInteger('record_type')->comment('SaasTransferRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('document_type')->nullable(false)->storedAs('1');
            $table->unsignedTinyInteger('original_payment_record_type')->nullable()->storedAs('CASE WHEN original_payment_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->unsignedTinyInteger('credit_note_document_type')->nullable()->storedAs('CASE WHEN credit_note_id IS NOT NULL THEN 2 ELSE NULL END');
            $table->unsignedTinyInteger('transfer_method')->comment('SaasTransferMethodEnum: 1, 2, 3, 4');
            $table->unsignedTinyInteger('transfer_status')->comment('SaasTransferStatusEnum: 1, 2, 3, 4, 5, 6, 7');
            $table->unsignedTinyInteger('refund_reason')->nullable()->comment('SaasRefundReasonEnum: 1, 2, 3, 4');
            $table->decimal('amount');
            $table->char('currency');
            $table->text('reason')->nullable();
            $table->string('transfer_reference')->nullable();
            $table->string('financial_account_key')->nullable();
            $table->char('transaction_fingerprint')->charset('ascii')->collation('ascii_bin')->nullable();
            $table->text('encrypted_transfer_details')->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->dateTime('sending_started_at')->nullable();
            $table->dateTime('validated_at')->nullable();
            $table->string('error_code')->nullable();
            $table->uuid('correlation_id');
            $table->timestamps();

            $table->unique(['id', 'document_id', 'user_id', 'record_type'], 'uq_saas_transfers_c2fd259498');
            $table->unique(['id', 'document_id', 'user_id', 'original_payment_id', 'record_type'], 'uq_saas_transfers_892616667f');
            $table->unique(['reversal_of_id'], 'uq_saas_transfers_d046da8d50');
            $table->index(['user_id', 'record_type', 'transfer_status', 'created_at', 'id'], 'ix_saas_transfers_16b7dff1cc');
            $table->index(['reversal_of_id', 'document_id', 'user_id', 'original_payment_id', 'record_type'], 'ix_saas_transfers_0a4417454a');
            $table->index(['document_id', 'record_type', 'transfer_status', 'id'], 'ix_saas_transfers_00834cb1b8');
            $table->index(['original_payment_id', 'record_type', 'transfer_status', 'id'], 'ix_saas_transfers_2622b7c9f3');
            $table->index(['credit_note_id', 'record_type', 'transfer_status', 'id'], 'ix_saas_transfers_5f908882cf');
            $table->index(['credit_note_id', 'document_id', 'user_id', 'credit_note_document_type'], 'ix_saas_transfers_c481bd880e');
            $table->index(['original_payment_id', 'document_id', 'user_id', 'original_payment_record_type'], 'ix_saas_transfers_dbda9bd81e');
            $table->index(['reversal_of_id', 'document_id', 'user_id', 'record_type'], 'ix_saas_transfers_277e987a88');
            $table->index(['document_id', 'user_id', 'document_type'], 'ix_saas_transfers_8293563717');
            $table->index(['correlation_id'], 'ix_saas_transfers_c787c4c20c');
            $table->index(['proof_media_id'], 'ix_saas_transfers_6aed88530f');
            $table->index(['source_proof_media_id'], 'ix_saas_transfers_2ac3d6fcc8');
            $table->index(['created_by_id'], 'ix_saas_transfers_6fb667974b');
            $table->index(['validated_by_id'], 'ix_saas_transfers_6a16de7f77');
            $table->index(['performed_by_id'], 'ix_saas_transfers_c71a2bb3e9');
            $table->foreign(['document_id', 'user_id', 'document_type'], 'fk_saas_transfers_8293563717')->references(['id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['credit_note_id', 'document_id', 'user_id', 'credit_note_document_type'], 'fk_saas_transfers_c481bd880e')->references(['id', 'original_invoice_id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_payment_id', 'document_id', 'user_id', 'original_payment_record_type'], 'fk_saas_transfers_dbda9bd81e')->references(['id', 'document_id', 'user_id', 'record_type'])->on('saas_transfers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'document_id', 'user_id', 'record_type'], 'fk_saas_transfers_277e987a88')->references(['id', 'document_id', 'user_id', 'record_type'])->on('saas_transfers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'document_id', 'user_id', 'original_payment_id', 'record_type'], 'fk_saas_transfers_0a4417454a')->references(['id', 'document_id', 'user_id', 'original_payment_id', 'record_type'])->on('saas_transfers')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_transfers');
    }
};
