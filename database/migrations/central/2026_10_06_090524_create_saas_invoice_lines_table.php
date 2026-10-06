<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('original_invoice_id')->nullable();
            $table->unsignedBigInteger('original_invoice_line_id')->nullable();
            $table->string('operation_key', 191)->unique();
            $table->unsignedTinyInteger('document_type')->comment('DocumentTypeEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('original_line_document_type')->nullable()->storedAs('CASE WHEN original_invoice_line_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->integer('line_number');
            $table->string('description');
            $table->decimal('quantity', 14, 2);
            $table->decimal('net_unit_price', 14, 2)->nullable();
            $table->decimal('net_discount', 14, 2)->nullable();
            $table->decimal('net_amount', 14, 2);
            $table->json('taxes');
            $table->decimal('tax_amount', 14, 2);
            $table->decimal('total_amount', 14, 2);
            $table->text('reason')->nullable();
            $table->char('correlation_id', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_invoice_lines');
    }
};
