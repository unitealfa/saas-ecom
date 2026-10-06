<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('return_id')->nullable();
            $table->unsignedBigInteger('incident_id');
            $table->unsignedBigInteger('credit_note_id')->nullable();
            $table->unsignedBigInteger('validated_by_id')->nullable();
            $table->unsignedBigInteger('proof_media_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unsignedBigInteger('correction_of_id')->nullable();
            $table->integer('compensated_quantity');
            $table->unsignedTinyInteger('amount_kind')->comment('AmountKindEnum: 1, 2, 3');
            $table->unsignedTinyInteger('type')->comment('AdjustmentTypeEnum: 1, 2, 3');
            $table->decimal('amount', 14, 2);
            $table->unsignedTinyInteger('status')->default(1)->comment('AdjustmentStatusEnum: 1, 2, 3, 4, 5');
            $table->dateTime('performed_at', 6)->nullable();
            $table->string('reference')->nullable();
            $table->text('reason');
            $table->string('operation_key');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_adjustments');
    }
};
