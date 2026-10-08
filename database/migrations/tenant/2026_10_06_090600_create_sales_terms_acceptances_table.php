<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_terms_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('order_id')->constrained('orders', indexName: 'fk_sales_terms_acceptances_ca13a6b2c9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('revision_id')->constrained('order_revisions', indexName: 'fk_sales_terms_acceptances_e22455d8ca')->restrictOnDelete()->restrictOnUpdate();
            $table->string('operation_key')->unique();
            $table->string('sales_terms_version');
            $table->char('terms_hash')->charset('ascii')->collation('ascii_bin');
            $table->dateTime('accepted_at');
            $table->unsignedTinyInteger('acceptance_mode')->comment('TermsAcceptanceModeEnum: 1, 2');
            $table->json('sanitized_proof')->nullable();
            $table->timestamp('created_at');

            $table->index(['revision_id', 'order_id'], 'ix_sales_terms_acceptances_ff6828c755');
            $table->index(['order_id'], 'ix_sales_terms_acceptances_ca13a6b2c9');
            $table->foreign(['revision_id', 'order_id'], 'fk_sales_terms_acceptances_ff6828c755')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_terms_acceptances');
    }
};
