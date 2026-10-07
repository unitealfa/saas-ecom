<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('shipped_revision_id');
            $table->unsignedBigInteger('order_item_id')->unique();
            $table->unsignedBigInteger('return_id')->nullable();
            $table->unsignedBigInteger('opened_by_id')->nullable();
            $table->unsignedBigInteger('validated_by_id')->nullable();
            $table->string('operation_key')->unique();
            $table->integer('affected_quantity');
            $table->decimal('eligible_product_amount', 14, 2);
            $table->decimal('eligible_shipping_amount', 14, 2);
            $table->unsignedTinyInteger('status')->default(1)->comment('IncidentStatusEnum: 1, 2, 3, 4, 5, 6');
            $table->text('reason');
            $table->dateTime('validated_at', 6)->nullable();
            $table->dateTime('closed_at', 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_incidents');
    }
};
