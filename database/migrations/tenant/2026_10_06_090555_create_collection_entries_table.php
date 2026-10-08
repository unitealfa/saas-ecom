<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('collection_id')->constrained('collections', indexName: 'fk_collection_entries_da719fe1c4')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('verified_by_id')->constrained('users', indexName: 'fk_collection_entries_b8ab8c1e46')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('proof_media_id')->nullable()->constrained('media', indexName: 'fk_collection_entries_6aed88530f')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('reversal_of_id')->nullable()->constrained('collection_entries', indexName: 'fk_collection_entries_d046da8d50')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('correction_of_id')->nullable()->constrained('collection_entries', indexName: 'fk_collection_entries_d0f90c8322')->restrictOnDelete()->restrictOnUpdate();
            $table->decimal('amount');
            $table->dateTime('collected_at');
            $table->dateTime('verified_at');
            $table->string('reference');
            $table->text('reason');
            $table->string('operation_key');
            $table->timestamp('created_at');

            $table->unique(['id', 'collection_id'], 'uq_collection_entries_622b560def');
            $table->unique(['operation_key'], 'uq_collection_entries_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_collection_entries_d046da8d50');
            $table->index(['collection_id', 'collected_at'], 'ix_collection_entries_678b81dca4');
            $table->index(['reversal_of_id', 'collection_id'], 'ix_collection_entries_0bea1a3527');
            $table->index(['correction_of_id', 'collection_id'], 'ix_collection_entries_30763314bb');
            $table->index(['verified_by_id'], 'ix_collection_entries_b8ab8c1e46');
            $table->index(['proof_media_id'], 'ix_collection_entries_6aed88530f');
            $table->foreign(['reversal_of_id', 'collection_id'], 'fk_collection_entries_0bea1a3527')->references(['id', 'collection_id'])->on('collection_entries')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'collection_id'], 'fk_collection_entries_30763314bb')->references(['id', 'collection_id'])->on('collection_entries')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_entries');
    }
};
