<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('validated_by_id')->nullable()->constrained('users', indexName: 'fk_billing_rules_6a16de7f77')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('record_type')->comment('TenantBillingRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('document_type')->nullable()->comment('DocumentTypeEnum: 1, 2, 3, 4, 5');
            $table->integer('fiscal_year')->nullable();
            $table->string('shop_prefix')->nullable();
            $table->bigInteger('next_number')->nullable();
            $table->unsignedTinyInteger('sequence_slot')->nullable()->storedAs('CASE WHEN record_type = 1 THEN 1 ELSE NULL END');
            $table->string('code')->nullable();
            $table->integer('version')->nullable();
            $table->unsignedBigInteger('seller_profile_version')->nullable();
            $table->string('trigger_event')->nullable();
            $table->string('return_resend_rule')->nullable();
            $table->string('numbering_scope')->nullable();
            $table->json('parameters')->nullable();
            $table->unsignedTinyInteger('policy_status')->nullable()->comment('PolicyStatusEnum: 1, 2, 3, 4');
            $table->text('validation_reference')->nullable();
            $table->dateTime('validated_at')->nullable();
            $table->dateTime('effective_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'document_type', 'fiscal_year', 'record_type'], 'uq_billing_rules_461178d473');
            $table->unique(['id', 'record_type'], 'uq_billing_rules_8e50b89e8a');
            $table->unique(['document_type', 'fiscal_year', 'sequence_slot'], 'uq_billing_rules_c8aa6027ef');
            $table->unique(['code', 'version'], 'uq_billing_rules_f5b433d863');
            $table->index(['record_type', 'code', 'policy_status', 'effective_at'], 'ix_billing_rules_d6e1239708');
            $table->index(['validated_by_id'], 'ix_billing_rules_6a16de7f77');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_rules');
    }
};
