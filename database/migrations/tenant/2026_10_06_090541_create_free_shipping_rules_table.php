<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_shipping_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->char('province_uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable();
            $table->string('name');
            $table->unsignedTinyInteger('delivery_mode')->nullable()->comment('DeliveryModeEnum: 1, 2');
            $table->decimal('minimum_cart_amount', 14, 2)->nullable();
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('ended_at', 6)->nullable();
            $table->integer('priority');
            $table->boolean('is_active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_shipping_rules');
    }
};
