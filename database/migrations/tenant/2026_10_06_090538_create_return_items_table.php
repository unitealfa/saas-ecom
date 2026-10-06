<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('return_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('shipped_revision_id');
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('inspected_by_id')->nullable();
            $table->integer('expected_quantity');
            $table->integer('received_quantity');
            $table->integer('restocked_quantity');
            $table->integer('lost_quantity');
            $table->integer('quarantined_quantity');
            $table->integer('documented_missing_quantity');
            $table->text('discrepancy_reason')->nullable();
            $table->decimal('unit_cost_snapshot', 14, 2);
            $table->dateTime('inspected_at', 6)->nullable();
            $table->text('note')->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
