<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (billing_rule_id, sequence_id, document_media_id) are constrained by 2026_10_06_091357_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('saas_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id')->constrained('users', indexName: 'fk_saas_invoices_f89d6b6960')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('subscription_id')->constrained('subscriptions', indexName: 'fk_saas_invoices_56c8a8bf72')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('installment_id')->constrained('subscriptions', indexName: 'fk_saas_invoices_b0a744b4f2')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('billing_rule_id');
            $table->foreignId('original_invoice_id')->nullable()->constrained('saas_invoices', indexName: 'fk_saas_invoices_ec080ce729')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('sequence_id')->nullable();
            $table->foreignId('document_media_id')->nullable();
            $table->string('number')->nullable()->unique();
            $table->string('operation_key')->unique();
            $table->unsignedTinyInteger('document_type')->comment('DocumentTypeEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('subscription_record_type')->nullable(false)->storedAs('1');
            $table->unsignedTinyInteger('installment_record_type')->nullable(false)->storedAs('2');
            $table->unsignedTinyInteger('billing_rule_record_type')->nullable(false)->storedAs('2');
            $table->unsignedTinyInteger('original_invoice_document_type')->nullable()->storedAs('CASE WHEN original_invoice_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->unsignedTinyInteger('sequence_record_type')->nullable()->storedAs('CASE WHEN sequence_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->json('billing_rule_snapshot');
            $table->integer('fiscal_year')->nullable();
            $table->bigInteger('sequence_number')->nullable();
            $table->decimal('net_amount');
            $table->json('taxes');
            $table->decimal('tax_amount');
            $table->decimal('total_amount');
            $table->char('currency');
            $table->unsignedTinyInteger('status')->default(1)->comment('DocumentStatusEnum: 1, 2, 3, 4');
            $table->text('reason')->nullable();
            $table->dateTime('period_starts_at');
            $table->dateTime('period_ends_at');
            $table->dateTime('due_at')->nullable();
            $table->json('saas_identity_snapshot');
            $table->json('customer_identity_snapshot');
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamps();

            $table->unique(['id', 'user_id', 'document_type'], 'uq_saas_invoices_55c06b53b4');
            $table->unique(['id', 'installment_id', 'subscription_id', 'user_id', 'document_type'], 'uq_saas_invoices_8dc1fbcf1c');
            $table->unique(['id', 'original_invoice_id', 'user_id', 'document_type'], 'uq_saas_invoices_a658339d67');
            $table->unique(['sequence_id', 'sequence_number'], 'uq_saas_invoices_ba5a89d2cf');
            $table->index(['original_invoice_id', 'installment_id', 'subscription_id', 'user_id', 'original_invoice_document_type'], 'ix_saas_invoices_2d79d283e4');
            $table->index(['user_id', 'document_type', 'issued_at', 'id'], 'ix_saas_invoices_ae0052dd0b');
            $table->index(['installment_id', 'subscription_id', 'user_id', 'installment_record_type'], 'ix_saas_invoices_b991245fce');
            $table->index(['sequence_id', 'document_type', 'fiscal_year', 'sequence_record_type'], 'ix_saas_invoices_b70ad3b9df');
            $table->index(['installment_id', 'document_type', 'status'], 'ix_saas_invoices_0f27c506e7');
            $table->index(['original_invoice_id', 'status', 'id'], 'ix_saas_invoices_913fccf823');
            $table->index(['subscription_id', 'user_id', 'subscription_record_type'], 'ix_saas_invoices_fcc56c4a07');
            $table->index(['original_invoice_id', 'user_id', 'original_invoice_document_type'], 'ix_saas_invoices_42bbb6432d');
            $table->index(['billing_rule_id', 'billing_rule_record_type'], 'ix_saas_invoices_1bae90140d');
            $table->index(['document_media_id'], 'ix_saas_invoices_1707541b1b');
            $table->foreign(['subscription_id', 'user_id', 'subscription_record_type'], 'fk_saas_invoices_fcc56c4a07')->references(['id', 'user_id', 'record_type'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['installment_id', 'subscription_id', 'user_id', 'installment_record_type'], 'fk_saas_invoices_b991245fce')->references(['id', 'parent_subscription_id', 'user_id', 'record_type'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_invoice_id', 'user_id', 'original_invoice_document_type'], 'fk_saas_invoices_42bbb6432d')->references(['id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_invoice_id', 'installment_id', 'subscription_id', 'user_id', 'original_invoice_document_type'], 'fk_saas_invoices_2d79d283e4')->references(['id', 'installment_id', 'subscription_id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_invoices');
    }
};
