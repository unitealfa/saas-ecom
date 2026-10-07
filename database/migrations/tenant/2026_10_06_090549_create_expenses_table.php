<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->unsignedBigInteger('return_id')->nullable();
            $table->unsignedBigInteger('proof_media_id')->nullable();
            $table->unsignedBigInteger('author_id');
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unsignedBigInteger('correction_of_id')->nullable();
            $table->string('category');
            $table->string('label');
            $table->decimal('amount', 14, 2);
            $table->dateTime('expense_date', 6);
            $table->unsignedTinyInteger('status')->default(1)->comment('ExpenseStatusEnum: 1, 2, 3, 4');
            $table->string('source');
            $table->string('operation_key');
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
