<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_history', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('order_id')->constrained('orders', indexName: 'fk_order_history_ca13a6b2c9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('previous_revision_id')->nullable()->constrained('order_revisions', indexName: 'fk_order_history_cb5d6a7448')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('next_revision_id')->nullable()->constrained('order_revisions', indexName: 'fk_order_history_4fbb76793e')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('actor_id')->nullable()->constrained('users', indexName: 'fk_order_history_fe4b4e1602')->restrictOnDelete()->restrictOnUpdate();
            $table->string('action');
            $table->unsignedTinyInteger('contact_outcome')->nullable()->comment('ContactOutcomeEnum: 1, 2, 3, 4, 5');
            $table->dateTime('next_callback_at')->nullable();
            $table->unsignedTinyInteger('previous_status')->nullable()->comment('OrderStatusEnum: 1, 2, 5');
            $table->unsignedTinyInteger('new_status')->nullable()->comment('OrderStatusEnum: 1, 2, 5');
            $table->json('changes')->nullable();
            $table->text('note')->nullable();
            $table->uuid('correlation_id');
            $table->unsignedTinyInteger('origin')->comment('ActivityOriginEnum: 1, 2, 3, 4');
            $table->timestamp('created_at');

            $table->index(['order_id', 'created_at'], 'ix_order_history_67479325e1');
            $table->index(['previous_revision_id', 'order_id'], 'ix_order_history_8cd5f69ee7');
            $table->index(['next_revision_id', 'order_id'], 'ix_order_history_3790f81ee9');
            $table->index(['actor_id'], 'ix_order_history_fe4b4e1602');
            $table->foreign(['previous_revision_id', 'order_id'], 'fk_order_history_8cd5f69ee7')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['next_revision_id', 'order_id'], 'fk_order_history_3790f81ee9')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_history');
    }
};
