<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('installment_id');
            $table->unsignedBigInteger('billing_rule_id');
            $table->unsignedBigInteger('original_invoice_id')->nullable();
            $table->unsignedBigInteger('sequence_id')->nullable();
            $table->unsignedBigInteger('document_media_id')->nullable();
            $table->string('number', 191)->nullable()->unique();
            $table->string('operation_key', 191)->unique();
            $table->unsignedTinyInteger('document_type')->comment('DocumentTypeEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('subscription_record_type')->nullable(false)->storedAs('1');
            $table->unsignedTinyInteger('installment_record_type')->nullable(false)->storedAs('2');
            $table->unsignedTinyInteger('billing_rule_record_type')->nullable(false)->storedAs('2');
            $table->unsignedTinyInteger('original_invoice_document_type')->nullable()->storedAs('CASE WHEN original_invoice_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->unsignedTinyInteger('sequence_record_type')->nullable()->storedAs('CASE WHEN sequence_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->json('billing_rule_snapshot');
            $table->integer('fiscal_year')->nullable();
            $table->bigInteger('sequence_number')->nullable();
            $table->decimal('net_amount', 14, 2);
            $table->json('taxes');
            $table->decimal('tax_amount', 14, 2);
            $table->decimal('total_amount', 14, 2);
            $table->char('currency', 3);
            $table->unsignedTinyInteger('status')->default(1)->comment('DocumentStatusEnum: 1, 2, 3, 4');
            $table->text('reason')->nullable();
            $table->dateTime('period_starts_at', 6);
            $table->dateTime('period_ends_at', 6);
            $table->dateTime('due_at', 6)->nullable();
            $table->json('saas_identity_snapshot');
            $table->json('customer_identity_snapshot');
            $table->dateTime('issued_at', 6)->nullable();
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->char('correlation_id', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_invoices');
    }
};
