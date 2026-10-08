<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (shipment_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('shipment_id');
            $table->foreignId('order_id')->constrained('orders', indexName: 'fk_order_returns_ca13a6b2c9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('shipped_revision_id')->constrained('order_revisions', indexName: 'fk_order_returns_f48a5da11a')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('received_by_id')->nullable()->constrained('users', indexName: 'fk_order_returns_9cd65364a6')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('reason')->comment('ReturnReasonEnum: 1, 2, 3, 4, 5, 6');
            $table->text('detail')->nullable();
            $table->unsignedTinyInteger('status')->default(1)->comment('ReturnStatusEnum: 1, 2, 3, 4, 5, 6');
            $table->dateTime('requested_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'shipment_id'], 'uq_order_returns_4baf6d51f5');
            $table->unique(['id', 'shipped_revision_id'], 'uq_order_returns_d3d9efb71e');
            $table->unique(['id', 'order_id'], 'uq_order_returns_cc60f48a93');
            $table->unique(['shipment_id'], 'uq_order_returns_9325aebf7e');
            $table->index(['shipment_id', 'order_id', 'shipped_revision_id'], 'ix_order_returns_b5f82f238f');
            $table->index(['order_id'], 'ix_order_returns_ca13a6b2c9');
            $table->index(['shipped_revision_id'], 'ix_order_returns_f48a5da11a');
            $table->index(['received_by_id'], 'ix_order_returns_9cd65364a6');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_returns');
    }
};
