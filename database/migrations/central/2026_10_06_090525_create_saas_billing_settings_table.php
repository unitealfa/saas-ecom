<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_billing_settings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('created_by_id')->nullable()->constrained('users', indexName: 'fk_saas_billing_settings_6fb667974b')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('validated_by_id')->nullable()->constrained('users', indexName: 'fk_saas_billing_settings_6a16de7f77')->restrictOnDelete()->restrictOnUpdate();
            $table->string('operation_key')->unique();
            $table->unsignedTinyInteger('record_type')->comment('SaasBillingSettingRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('document_type')->nullable()->comment('DocumentTypeEnum: 1, 2, 3, 4, 5');
            $table->integer('fiscal_year')->nullable();
            $table->string('prefix')->nullable();
            $table->bigInteger('next_number')->nullable();
            $table->unsignedTinyInteger('sequence_slot')->nullable()->storedAs('CASE WHEN record_type = 1 THEN 1 ELSE NULL END');
            $table->string('code')->nullable();
            $table->integer('version')->nullable();
            $table->string('trigger_event')->nullable();
            $table->string('numbering_scope')->nullable();
            $table->json('parameters')->nullable();
            $table->unsignedTinyInteger('policy_status')->nullable()->comment('PolicyStatusEnum: 1, 2, 3, 4');
            $table->text('validation_reference')->nullable();
            $table->dateTime('effective_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('validated_at')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamps();

            $table->unique(['id', 'record_type'], 'uq_saas_billing_settings_8e50b89e8a');
            $table->unique(['id', 'document_type', 'fiscal_year', 'record_type'], 'uq_saas_billing_settings_461178d473');
            $table->unique(['document_type', 'fiscal_year', 'sequence_slot'], 'uq_saas_billing_settings_c8aa6027ef');
            $table->unique(['code', 'version'], 'uq_saas_billing_settings_f5b433d863');
            $table->index(['code', 'policy_status', 'effective_at'], 'ix_saas_billing_settings_a2842e9e62');
            $table->index(['correlation_id'], 'ix_saas_billing_settings_c787c4c20c');
            $table->index(['created_by_id'], 'ix_saas_billing_settings_6fb667974b');
            $table->index(['validated_by_id'], 'ix_saas_billing_settings_6a16de7f77');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_settings');
    }
};
