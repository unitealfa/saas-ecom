<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_remittance_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('carrier_account_id')->constrained('carrier_accounts', indexName: 'fk_carrier_remittance_batches_fd68d11272')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('proof_media_id')->nullable()->constrained('media', indexName: 'fk_carrier_remittance_batches_6aed88530f')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('reversal_of_id')->nullable()->constrained('carrier_remittance_batches', indexName: 'fk_carrier_remittance_batches_d046da8d50')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('validated_by_id')->nullable()->constrained('users', indexName: 'fk_carrier_remittance_batches_6a16de7f77')->restrictOnDelete()->restrictOnUpdate();
            $table->string('operation_key')->unique();
            $table->string('external_reference')->nullable();
            $table->decimal('reported_account_net_amount')->nullable();
            $table->decimal('computed_shop_net_amount');
            $table->decimal('verified_net_amount')->nullable();
            $table->unsignedTinyInteger('status')->default(1)->comment('RemittanceBatchStatusEnum: 1, 2, 3');
            $table->dateTime('received_at')->nullable();
            $table->timestamps();

            $table->unique(['carrier_account_id', 'external_reference'], 'uq_carrier_remittance_batches_d428b45b18');
            $table->unique(['id', 'carrier_account_id'], 'uq_carrier_remittance_batches_d5e203b348');
            $table->unique(['reversal_of_id'], 'uq_carrier_remittance_batches_d046da8d50');
            $table->index(['carrier_account_id', 'status', 'received_at'], 'ix_carrier_remittance_batches_774f9fe1b3');
            $table->index(['reversal_of_id', 'carrier_account_id'], 'ix_carrier_remittance_batches_5f7d97d0bf');
            $table->index(['proof_media_id'], 'ix_carrier_remittance_batches_6aed88530f');
            $table->index(['validated_by_id'], 'ix_carrier_remittance_batches_6a16de7f77');
            $table->foreign(['reversal_of_id', 'carrier_account_id'], 'fk_carrier_remittance_batches_5f7d97d0bf')->references(['id', 'carrier_account_id'])->on('carrier_remittance_batches')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_remittance_batches');
    }
};
