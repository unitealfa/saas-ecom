<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_correction_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('correction_id')->constrained('commercial_corrections', indexName: 'fk_commercial_correction_lines_b86c400c6c')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('source_revision_id')->constrained('order_revisions', indexName: 'fk_commercial_correction_lines_d329d3e500')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('order_item_id')->constrained('order_items', indexName: 'fk_commercial_correction_lines_f63d0d3350')->restrictOnDelete()->restrictOnUpdate();
            $table->integer('affected_quantity');
            $table->decimal('reference_sale_amount');
            $table->decimal('revenue_delta');
            $table->decimal('sold_cost_delta');
            $table->text('detailed_reason')->nullable();
            $table->timestamp('created_at');

            $table->unique(['correction_id', 'order_item_id'], 'uq_commercial_correction_lines_7a03ec309d');
            $table->index(['correction_id', 'source_revision_id'], 'ix_commercial_correction_lines_a8c9aa0cb7');
            $table->index(['order_item_id', 'source_revision_id'], 'ix_commercial_correction_lines_37c2fc4d66');
            $table->index(['source_revision_id'], 'ix_commercial_correction_lines_d329d3e500');
            $table->foreign(['correction_id', 'source_revision_id'], 'fk_commercial_correction_lines_a8c9aa0cb7')->references(['id', 'source_revision_id'])->on('commercial_corrections')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id', 'source_revision_id'], 'fk_commercial_correction_lines_37c2fc4d66')->references(['id', 'revision_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_correction_lines');
    }
};
