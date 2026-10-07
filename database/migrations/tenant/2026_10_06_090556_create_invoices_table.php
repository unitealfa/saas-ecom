<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('revision_id');
            $table->unsignedBigInteger('original_invoice_id')->nullable();
            $table->unsignedBigInteger('sequence_id')->nullable();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->unsignedBigInteger('issued_by_id')->nullable();
            $table->unsignedBigInteger('incident_id')->nullable();
            $table->unsignedTinyInteger('document_type')->comment('DocumentTypeEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('sequence_record_type')->nullable()->storedAs('CASE WHEN sequence_id IS NOT NULL THEN 1 ELSE NULL END')->comment('TenantBillingRecordTypeEnum: 1, 2');
            $table->integer('fiscal_year')->nullable();
            $table->bigInteger('sequence_number')->nullable();
            $table->integer('snapshot_format_version');
            $table->char('currency', 3);
            $table->string('number')->nullable();
            $table->unsignedTinyInteger('status')->default(1)->comment('DocumentStatusEnum: 1, 2, 3, 4');
            $table->json('seller_snapshot');
            $table->json('client_snapshot');
            $table->json('items_snapshot');
            $table->json('totals_snapshot');
            $table->dateTime('issued_at', 6)->nullable();
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('operation_key');
            $table->string('document_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
