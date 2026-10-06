<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_obligations', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('revision_id');
            $table->unsignedBigInteger('billing_rule_id');
            $table->unsignedBigInteger('original_invoice_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('operation_key')->unique();
            $table->unsignedBigInteger('event_id');
            $table->unsignedTinyInteger('billing_rule_record_type')->nullable(false)->storedAs('CASE WHEN billing_rule_id IS NOT NULL THEN 2 ELSE NULL END')->comment('TenantBillingRecordTypeEnum: 1, 2');
            $table->json('rule_snapshot');
            $table->string('event_type');
            $table->dateTime('triggered_at', 6);
            $table->unsignedTinyInteger('document_type')->comment('DocumentTypeEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('status')->default(1)->comment('BillingObligationStatusEnum: 1, 2, 3, 4, 5');
            $table->integer('attempts_count');
            $table->dateTime('next_attempt_at', 6)->nullable();
            $table->string('error_code')->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_obligations');
    }
};
 