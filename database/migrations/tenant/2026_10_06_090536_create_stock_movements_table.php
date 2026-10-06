<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('return_item_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->bigInteger('variant_sequence');
            $table->unsignedTinyInteger('type')->comment('StockMovementTypeEnum: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11');
            $table->integer('physical_delta');
            $table->integer('reserved_delta');
            $table->integer('quarantine_delta');
            $table->integer('return_received_delta');
            $table->integer('return_restocked_delta');
            $table->integer('return_lost_delta');
            $table->integer('return_missing_delta')->default(0);
            $table->integer('physical_before');
            $table->integer('physical_after');
            $table->integer('reserved_before');
            $table->integer('reserved_after');
            $table->integer('quarantine_before');
            $table->integer('quarantine_after');
            $table->decimal('unit_cost_snapshot', 14, 2);
            $table->decimal('loss_amount', 14, 2);
            $table->string('operation_key');
            $table->char('correlation_id', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->text('note')->nullable();
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
