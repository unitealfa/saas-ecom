<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (proof_media_id) are constrained by 2026_10_06_091357_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('saas_document_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id')->constrained('users', indexName: 'fk_saas_document_deliveries_f89d6b6960')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('document_id')->constrained('saas_invoices', indexName: 'fk_saas_document_deliveries_2a8e659395')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('created_by_id')->nullable()->constrained('users', indexName: 'fk_saas_document_deliveries_6fb667974b')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('proof_media_id')->nullable();
            $table->string('operation_key')->unique();
            $table->unsignedTinyInteger('document_type')->comment('DocumentTypeEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('channel')->comment('DocumentDeliveryChannelEnum: 1, 2, 3, 4');
            $table->text('encrypted_recipient');
            $table->unsignedTinyInteger('delivery_status')->comment('DocumentDeliveryStatusEnum: 1, 2, 3, 4, 5, 6, 7, 8');
            $table->integer('attempts_count');
            $table->json('delivery_attempts')->nullable();
            $table->dateTime('next_attempt_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('error_code')->nullable();
            $table->dateTime('sending_started_at')->nullable();
            $table->uuid('correlation_id');
            $table->timestamps();

            $table->index(['delivery_status', 'next_attempt_at', 'id'], 'ix_saas_document_deliveries_23e581b097');
            $table->index(['document_id', 'created_at', 'id'], 'ix_saas_document_deliveries_67467a6653');
            $table->index(['document_id', 'user_id', 'document_type'], 'ix_saas_document_deliveries_8293563717');
            $table->index(['correlation_id'], 'ix_saas_document_deliveries_c787c4c20c');
            $table->index(['user_id'], 'ix_saas_document_deliveries_f89d6b6960');
            $table->index(['created_by_id'], 'ix_saas_document_deliveries_6fb667974b');
            $table->index(['proof_media_id'], 'ix_saas_document_deliveries_6aed88530f');
            $table->foreign(['document_id', 'user_id', 'document_type'], 'fk_saas_document_deliveries_8293563717')->references(['id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_document_deliveries');
    }
};
