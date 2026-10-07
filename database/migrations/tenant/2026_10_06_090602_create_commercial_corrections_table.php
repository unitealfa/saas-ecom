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
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('source_revision_id');
            $table->unsignedBigInteger('incident_id')->nullable();
            $table->unsignedBigInteger('correction_of_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('operation_key')->unique();
            $table->unsignedTinyInteger('correction_type')->comment('CommercialCorrectionTypeEnum: 1, 2, 4, 5, 6, 7');
            $table->unsignedTinyInteger('status')->default(1)->comment('CommercialCorrectionStatusEnum: 1, 2, 3, 4');
            $table->decimal('non_product_revenue_delta', 14, 2)->default(0);
            $table->unsignedTinyInteger('non_product_kind')->comment('NonProductKindEnum: 1, 2, 3, 4');
            $table->dateTime('effective_at', 6);
            $table->dateTime('recorded_at', 6);
            $table->text('reason');
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_corrections');
    }
};
