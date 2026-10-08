<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_obligations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('order_id')->constrained('orders', indexName: 'fk_billing_obligations_ca13a6b2c9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('revision_id')->constrained('order_revisions', indexName: 'fk_billing_obligations_e22455d8ca')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('billing_rule_id')->constrained('billing_rules', indexName: 'fk_billing_obligations_7de4923723')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('original_invoice_id')->nullable()->constrained('invoices', indexName: 'fk_billing_obligations_ec080ce729')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices', indexName: 'fk_billing_obligations_16a54288bc')->restrictOnDelete()->restrictOnUpdate();
            $table->string('operation_key')->unique();
            $table->unsignedBigInteger('event_id');
            $table->unsignedTinyInteger('billing_rule_record_type')->nullable(false)->storedAs('CASE WHEN billing_rule_id IS NOT NULL THEN 2 ELSE NULL END')->comment('TenantBillingRecordTypeEnum: 1, 2');
            $table->json('rule_snapshot');
            $table->string('event_type');
            $table->dateTime('triggered_at');
            $table->unsignedTinyInteger('document_type')->comment('DocumentTypeEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('status')->default(1)->comment('BillingObligationStatusEnum: 1, 2, 3, 4, 5');
            $table->integer('attempts_count');
            $table->dateTime('next_attempt_at')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id'], 'uq_billing_obligations_16a54288bc');
            $table->index(['invoice_id', 'order_id', 'revision_id', 'document_type', 'original_invoice_id'], 'ix_billing_obligations_0291334460');
            $table->index(['status', 'next_attempt_at'], 'ix_billing_obligations_bef5422393');
            $table->index(['billing_rule_id', 'billing_rule_record_type'], 'ix_billing_obligations_1bae90140d');
            $table->index(['revision_id', 'order_id'], 'ix_billing_obligations_ff6828c755');
            $table->index(['order_id'], 'ix_billing_obligations_ca13a6b2c9');
            $table->index(['original_invoice_id'], 'ix_billing_obligations_ec080ce729');
            $table->foreign(['billing_rule_id', 'billing_rule_record_type'], 'fk_billing_obligations_1bae90140d')->references(['id', 'record_type'])->on('billing_rules')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['revision_id', 'order_id'], 'fk_billing_obligations_ff6828c755')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['invoice_id', 'order_id', 'revision_id', 'document_type'], 'fk_billing_obligations_69ee8cdbb1')->references(['id', 'order_id', 'revision_id', 'document_type'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['invoice_id', 'order_id', 'revision_id', 'document_type', 'original_invoice_id'], 'fk_billing_obligations_0291334460')->references(['id', 'order_id', 'revision_id', 'document_type', 'original_invoice_id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_obligations');
    }
};
