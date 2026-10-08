<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_corrections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('order_id')->constrained('orders', indexName: 'fk_commercial_corrections_ca13a6b2c9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('source_revision_id')->constrained('order_revisions', indexName: 'fk_commercial_corrections_d329d3e500')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('incident_id')->nullable()->constrained('order_incidents', indexName: 'fk_commercial_corrections_04dbd90085')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('correction_of_id')->nullable()->constrained('commercial_corrections', indexName: 'fk_commercial_corrections_d0f90c8322')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('actor_id')->nullable()->constrained('users', indexName: 'fk_commercial_corrections_fe4b4e1602')->restrictOnDelete()->restrictOnUpdate();
            $table->string('operation_key')->unique();
            $table->unsignedTinyInteger('correction_type')->comment('CommercialCorrectionTypeEnum: 1, 2, 4, 5, 6, 7');
            $table->unsignedTinyInteger('status')->default(1)->comment('CommercialCorrectionStatusEnum: 1, 2, 3, 4');
            $table->decimal('non_product_revenue_delta')->default(0);
            $table->unsignedTinyInteger('non_product_kind')->comment('NonProductKindEnum: 1, 2, 3, 4');
            $table->dateTime('effective_at');
            $table->dateTime('recorded_at');
            $table->text('reason');
            $table->timestamp('created_at');

            $table->unique(['id', 'order_id', 'source_revision_id'], 'uq_commercial_corrections_0885846f78');
            $table->unique(['correction_of_id'], 'uq_commercial_corrections_d0f90c8322');
            $table->unique(['id', 'source_revision_id'], 'uq_commercial_corrections_602a26090c');
            $table->index(['correction_of_id', 'order_id', 'source_revision_id'], 'ix_commercial_corrections_8834292179');
            $table->index(['status', 'effective_at'], 'ix_commercial_corrections_f4dd9c9e5d');
            $table->index(['source_revision_id', 'order_id'], 'ix_commercial_corrections_a5c7e0ed56');
            $table->index(['incident_id', 'order_id'], 'ix_commercial_corrections_4e2d7fc88d');
            $table->index(['order_id'], 'ix_commercial_corrections_ca13a6b2c9');
            $table->index(['actor_id'], 'ix_commercial_corrections_fe4b4e1602');
            $table->foreign(['correction_of_id', 'order_id', 'source_revision_id'], 'fk_commercial_corrections_8834292179')->references(['id', 'order_id', 'source_revision_id'])->on('commercial_corrections')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['source_revision_id', 'order_id'], 'fk_commercial_corrections_a5c7e0ed56')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['incident_id', 'order_id'], 'fk_commercial_corrections_4e2d7fc88d')->references(['id', 'order_id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_corrections');
    }
};
