<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_providers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('carrier_account_id')->nullable();
            $table->unsignedTinyInteger('type')->comment('ShippingProviderTypeEnum: 1, 2, 3');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('reference_configuration')->nullable();
            $table->dateTime('last_synced_at', 6)->nullable();
            $table->boolean('is_active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_providers');
    }
};
