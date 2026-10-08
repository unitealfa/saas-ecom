<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (sequence_id, incident_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('order_id')->constrained('orders', indexName: 'fk_invoices_ca13a6b2c9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('revision_id')->constrained('order_revisions', indexName: 'fk_invoices_e22455d8ca')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('original_invoice_id')->nullable()->constrained('invoices', indexName: 'fk_invoices_ec080ce729')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('sequence_id')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media', indexName: 'fk_invoices_d3fc3e3e3a')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('issued_by_id')->nullable()->constrained('users', indexName: 'fk_invoices_35693fc42b')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('incident_id')->nullable();
            $table->unsignedTinyInteger('document_type')->comment('DocumentTypeEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('sequence_record_type')->nullable()->storedAs('CASE WHEN sequence_id IS NOT NULL THEN 1 ELSE NULL END')->comment('TenantBillingRecordTypeEnum: 1, 2');
            $table->integer('fiscal_year')->nullable();
            $table->bigInteger('sequence_number')->nullable();
            $table->integer('snapshot_format_version');
            $table->char('currency', 3);
            $table->string('number')->nullable();
            $table->unsignedTinyInteger('status')->default(1)->comment('DocumentStatusEnum: 1, 2, 3, 4');
            $table->json('seller_snapshot');
            $table->json('client_snapshot');
            $table->json('items_snapshot');
            $table->json('totals_snapshot');
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('operation_key');
            $table->string('document_reason')->nullable();
            $table->timestamps();

            $table->unique(['id', 'order_id'], 'uq_invoices_cc60f48a93');
            $table->unique(['id', 'order_id', 'revision_id', 'document_type'], 'uq_invoices_427443939d');
            $table->unique(['id', 'order_id', 'revision_id', 'document_type', 'original_invoice_id'], 'uq_invoices_968d27f2e4');
            $table->unique(['number'], 'uq_invoices_12886f9d00');
            $table->unique(['sequence_id', 'sequence_number'], 'uq_invoices_ba5a89d2cf');
            $table->unique(['operation_key'], 'uq_invoices_c8ff3469da');
            $table->index(['sequence_id', 'document_type', 'fiscal_year', 'sequence_record_type'], 'ix_invoices_b70ad3b9df');
            $table->index(['revision_id', 'order_id'], 'ix_invoices_ff6828c755');
            $table->index(['original_invoice_id', 'order_id'], 'ix_invoices_3a27fee5c5');
            $table->index(['incident_id', 'order_id'], 'ix_invoices_4e2d7fc88d');
            $table->index(['order_id'], 'ix_invoices_ca13a6b2c9');
            $table->index(['media_id'], 'ix_invoices_d3fc3e3e3a');
            $table->index(['issued_by_id'], 'ix_invoices_35693fc42b');
            $table->foreign(['revision_id', 'order_id'], 'fk_invoices_ff6828c755')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_invoice_id', 'order_id'], 'fk_invoices_3a27fee5c5')->references(['id', 'order_id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
