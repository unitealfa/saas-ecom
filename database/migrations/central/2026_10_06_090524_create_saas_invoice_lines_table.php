<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('document_id')->constrained('saas_invoices', indexName: 'fk_saas_invoice_lines_2a8e659395')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('user_id')->constrained('users', indexName: 'fk_saas_invoice_lines_f89d6b6960')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('original_invoice_id')->nullable()->constrained('saas_invoices', indexName: 'fk_saas_invoice_lines_ec080ce729')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('original_invoice_line_id')->nullable()->constrained('saas_invoice_lines', indexName: 'fk_saas_invoice_lines_7f3244d890')->restrictOnDelete()->restrictOnUpdate();
            $table->string('operation_key')->unique();
            $table->unsignedTinyInteger('document_type')->comment('DocumentTypeEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('original_line_document_type')->nullable()->storedAs('CASE WHEN original_invoice_line_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->integer('line_number');
            $table->string('description');
            $table->decimal('quantity');
            $table->decimal('net_unit_price')->nullable();
            $table->decimal('net_discount')->nullable();
            $table->decimal('net_amount');
            $table->json('taxes');
            $table->decimal('tax_amount');
            $table->decimal('total_amount');
            $table->text('reason')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamps();

            $table->unique(['id', 'document_id', 'user_id', 'document_type'], 'uq_saas_invoice_lines_77a3b1856b');
            $table->unique(['document_id', 'line_number'], 'uq_saas_invoice_lines_533c361e24');
            $table->unique(['document_id', 'original_invoice_line_id'], 'uq_saas_invoice_lines_c60d2137ae');
            $table->index(['document_id', 'original_invoice_id', 'user_id', 'document_type'], 'ix_saas_invoice_lines_cdd1b4ed69');
            $table->index(['original_invoice_line_id', 'original_invoice_id', 'user_id', 'original_line_document_type'], 'ix_saas_invoice_lines_2dfa389c8b');
            $table->index(['document_id', 'user_id', 'document_type'], 'ix_saas_invoice_lines_8293563717');
            $table->index(['original_invoice_line_id', 'document_id'], 'ix_saas_invoice_lines_cfc183580c');
            $table->index(['user_id'], 'ix_saas_invoice_lines_f89d6b6960');
            $table->index(['original_invoice_id'], 'ix_saas_invoice_lines_ec080ce729');
            $table->foreign(['document_id', 'user_id', 'document_type'], 'fk_saas_invoice_lines_8293563717')->references(['id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['document_id', 'original_invoice_id', 'user_id', 'document_type'], 'fk_saas_invoice_lines_cdd1b4ed69')->references(['id', 'original_invoice_id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_invoice_line_id', 'original_invoice_id', 'user_id', 'original_line_document_type'], 'fk_saas_invoice_lines_2dfa389c8b')->references(['id', 'document_id', 'user_id', 'document_type'])->on('saas_invoice_lines')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_invoice_lines');
    }
};
