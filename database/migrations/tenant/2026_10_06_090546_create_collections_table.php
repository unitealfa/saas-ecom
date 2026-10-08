<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('shipment_id')->constrained('shipments', indexName: 'fk_collections_9325aebf7e')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('declared_status')->comment('CollectionStatusEnum: 1, 2, 3, 4, 5, 6, 7');
            $table->decimal('expected_amount');
            $table->decimal('declared_collected_amount')->nullable();
            $table->dateTime('collected_at')->nullable();
            $table->dateTime('payment_ready_at')->nullable();
            $table->dateTime('declared_paid_at')->nullable();
            $table->string('source');
            $table->dateTime('reconciled_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'shipment_id'], 'uq_collections_4baf6d51f5');
            $table->unique(['shipment_id'], 'uq_collections_9325aebf7e');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
