<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_carriers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('adapter');
            $table->string('default_api_url')->nullable();
            $table->boolean('is_active');
            $table->string('reference_source');
            $table->integer('reference_version');
            $table->dateTime('synced_at', 6)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_carriers');
    }
};
