<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('product_id')->nullable()->constrained('products', indexName: 'fk_expenses_c3adad4f81')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('order_id')->nullable()->constrained('orders', indexName: 'fk_expenses_ca13a6b2c9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments', indexName: 'fk_expenses_9325aebf7e')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('return_id')->nullable()->constrained('order_returns', indexName: 'fk_expenses_4ee7679ca5')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('proof_media_id')->nullable()->constrained('media', indexName: 'fk_expenses_6aed88530f')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('author_id')->constrained('users', indexName: 'fk_expenses_378e66e226')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('reversal_of_id')->nullable()->constrained('expenses', indexName: 'fk_expenses_d046da8d50')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('correction_of_id')->nullable()->constrained('expenses', indexName: 'fk_expenses_d0f90c8322')->restrictOnDelete()->restrictOnUpdate();
            $table->string('category');
            $table->string('label');
            $table->decimal('amount');
            $table->dateTime('expense_date');
            $table->unsignedTinyInteger('status')->default(1)->comment('ExpenseStatusEnum: 1, 2, 3, 4');
            $table->string('source');
            $table->string('operation_key');
            $table->dateTime('cancelled_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['operation_key'], 'uq_expenses_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_expenses_d046da8d50');
            $table->index(['expense_date', 'product_id'], 'ix_expenses_ee736baad5');
            $table->index(['shipment_id'], 'ix_expenses_9325aebf7e');
            $table->index(['return_id'], 'ix_expenses_4ee7679ca5');
            $table->index(['product_id'], 'ix_expenses_c3adad4f81');
            $table->index(['order_id'], 'ix_expenses_ca13a6b2c9');
            $table->index(['proof_media_id'], 'ix_expenses_6aed88530f');
            $table->index(['author_id'], 'ix_expenses_378e66e226');
            $table->index(['correction_of_id'], 'ix_expenses_d0f90c8322');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
