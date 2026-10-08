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
            $table->uuid('uuid');
            $table->foreignId('shop_id')->constrained('shop', indexName: 'fk_shop_addresses_176fd1ac1e')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('shop_address_id')->nullable()->constrained('shop_addresses', indexName: 'fk_shop_addresses_e08e4d9849')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('province_uuid')->nullable();
            $table->uuid('municipality_uuid')->nullable();
            $table->unsignedTinyInteger('record_type')->comment('ShopProfileRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('shop_address_type')->nullable()->storedAs('CASE WHEN shop_address_id IS NOT NULL THEN 1 ELSE NULL END')->comment('ShopProfileRecordTypeEnum: 1, 2');
            $table->string('label')->nullable();
            $table->integer('position');
            $table->boolean('is_primary');
            $table->boolean('visible');
            $table->json('payload');
            $table->unsignedTinyInteger('primary_slot')->nullable()->storedAs('CASE WHEN record_type = 1 AND is_primary = TRUE AND deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'shop_id', 'record_type'], 'uq_shop_addresses_35c9ecc8c2');
            $table->unique(['shop_id', 'primary_slot'], 'uq_shop_addresses_95137c0c10');
            $table->index(['shop_id', 'record_type', 'deleted_at', 'visible', 'position', 'id'], 'ix_shop_addresses_458d0048ae');
            $table->index(['shop_address_id', 'shop_id', 'shop_address_type'], 'ix_shop_addresses_88543b6ab4');
            $table->foreign(['shop_address_id', 'shop_id', 'shop_address_type'], 'fk_shop_addresses_88543b6ab4')->references(['id', 'shop_id', 'record_type'])->on('shop_addresses')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_addresses');
    }
};
