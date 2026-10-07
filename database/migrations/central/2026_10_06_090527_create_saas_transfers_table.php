<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('original_payment_id')->nullable();
            $table->unsignedBigInteger('credit_note_id')->nullable();
            $table->unsignedBigInteger('proof_media_id')->nullable();
            $table->unsignedBigInteger('source_proof_media_id')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('validated_by_id')->nullable();
            $table->unsignedBigInteger('performed_by_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->char('active_transaction_fingerprint', 64)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable()->storedAs('CASE WHEN transfer_status = 3 AND reversal_of_id IS NULL THEN transaction_fingerprint ELSE NULL END')->unique();
            $table->string('operation_key', 191)->unique();
            $table->unsignedTinyInteger('record_type')->comment('SaasTransferRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('document_type')->nullable(false)->storedAs('1');
            $table->unsignedTinyInteger('original_payment_record_type')->nullable()->storedAs('CASE WHEN original_payment_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->unsignedTinyInteger('credit_note_document_type')->nullable()->storedAs('CASE WHEN credit_note_id IS NOT NULL THEN 2 ELSE NULL END');
            $table->unsignedTinyInteger('transfer_method')->comment('SaasTransferMethodEnum: 1, 2, 3, 4');
            $table->unsignedTinyInteger('transfer_status')->comment('SaasTransferStatusEnum: 1, 2, 3, 4, 5, 6, 7');
            $table->unsignedTinyInteger('refund_reason')->nullable()->comment('SaasRefundReasonEnum: 1, 2, 3, 4');
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3);
            $table->text('reason')->nullable();
            $table->string('transfer_reference', 191)->nullable();
            $table->string('financial_account_key', 64)->nullable();
            $table->char('transaction_fingerprint', 64)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable();
            $table->text('encrypted_transfer_details')->nullable();
            $table->dateTime('occurred_at', 6)->nullable();
            $table->dateTime('sending_started_at', 6)->nullable();
            $table->dateTime('validated_at', 6)->nullable();
            $table->string('error_code')->nullable();
            $table->char('correlation_id', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_transfers');
    }
};
