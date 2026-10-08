<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('order_id')->constrained('orders', indexName: 'fk_order_documents_ca13a6b2c9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('revision_id')->constrained('order_revisions', indexName: 'fk_order_documents_e22455d8ca')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('media_id')->constrained('media', indexName: 'fk_order_documents_d3fc3e3e3a')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('generated_by_id')->constrained('users', indexName: 'fk_order_documents_dceaab373c')->restrictOnDelete()->restrictOnUpdate();
            $table->string('number');
            $table->integer('document_version');
            $table->json('issuer_snapshot');
            $table->dateTime('generated_at');
            $table->timestamp('created_at');

            $table->unique(['number', 'document_version'], 'uq_order_documents_866a3a6347');
            $table->index(['revision_id', 'order_id'], 'ix_order_documents_ff6828c755');
            $table->index(['order_id'], 'ix_order_documents_ca13a6b2c9');
            $table->index(['media_id'], 'ix_order_documents_d3fc3e3e3a');
            $table->index(['generated_by_id'], 'ix_order_documents_dceaab373c');
            $table->foreign(['revision_id', 'order_id'], 'fk_order_documents_ff6828c755')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_documents');
    }
};
