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
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('validated_by_id')->nullable();
            $table->unsignedTinyInteger('record_type')->comment('TenantBillingRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('document_type')->nullable()->comment('DocumentTypeEnum: 1, 2, 3, 4, 5');
            $table->integer('fiscal_year')->nullable();
            $table->string('shop_prefix', 32)->nullable();
            $table->bigInteger('next_number')->nullable();
            $table->unsignedTinyInteger('sequence_slot')->nullable()->storedAs('CASE WHEN record_type = 1 THEN 1 ELSE NULL END');
            $table->string('code', 100)->nullable();
            $table->integer('version')->nullable();
            $table->unsignedBigInteger('seller_profile_version')->nullable();
            $table->string('trigger_event')->nullable();
            $table->string('return_resend_rule')->nullable();
            $table->string('numbering_scope')->nullable();
            $table->json('parameters')->nullable();
            $table->unsignedTinyInteger('policy_status')->nullable()->comment('PolicyStatusEnum: 1, 2, 3, 4');
            $table->text('validation_reference')->nullable();
            $table->dateTime('validated_at', 6)->nullable();
            $table->dateTime('effective_at', 6)->nullable();
            $table->dateTime('ends_at', 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_rules');
    }
};
