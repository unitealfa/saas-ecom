<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_addresses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('shop_address_id')->nullable();
            $table->char('province_uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable();
            $table->char('municipality_uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable();
            $table->unsignedTinyInteger('record_type')->comment('ShopProfileRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('shop_address_type')->nullable()->storedAs('CASE WHEN shop_address_id IS NOT NULL THEN 1 ELSE NULL END')->comment('ShopProfileRecordTypeEnum: 1, 2');
            $table->string('label')->nullable();
            $table->integer('position');
            $table->boolean('is_primary');
            $table->boolean('visible');
            $table->json('payload');
            $table->unsignedTinyInteger('primary_slot')->nullable()->storedAs('CASE WHEN record_type = 1 AND is_primary = 1 AND deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_addresses');
    }
};
