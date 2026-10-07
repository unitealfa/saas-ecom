<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('shipped_revision_id');
            $table->unsignedBigInteger('received_by_id')->nullable();
            $table->unsignedTinyInteger('reason')->comment('ReturnReasonEnum: 1, 2, 3, 4, 5, 6');
            $table->text('detail')->nullable();
            $table->unsignedTinyInteger('status')->default(1)->comment('ReturnStatusEnum: 1, 2, 3, 4, 5, 6');
            $table->dateTime('requested_at', 6)->nullable();
            $table->dateTime('received_at', 6)->nullable();
            $table->dateTime('closed_at', 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_returns');
    }
};
