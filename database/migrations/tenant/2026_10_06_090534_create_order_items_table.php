<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('revision_id');
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->unsignedBigInteger('sales_page_id')->nullable();
            $table->string('product_name');
            $table->string('variant_name');
            $table->string('sku');
            $table->json('options_snapshot')->nullable();
            $table->text('customization_text')->nullable();
            $table->integer('quantity');
            $table->decimal('catalog_unit_price', 14, 2);
            $table->decimal('applied_unit_price', 14, 2);
            $table->boolean('is_price_overridden');
            $table->text('price_change_reason')->nullable();
            $table->unsignedTinyInteger('price_origin')->comment('PriceOriginEnum: 1, 2, 3');
            $table->json('promotion_snapshot')->nullable();
            $table->decimal('unit_cost_snapshot', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->json('tax_snapshot');
            $table->unsignedTinyInteger('reservation_status')->nullable()->comment('StockReservationStatusEnum: 1, 2, 3');
            $table->dateTime('reserved_at', 6)->nullable();
            $table->dateTime('reservation_released_at', 6)->nullable();
            $table->dateTime('reservation_created_at', 6)->nullable();
            $table->dateTime('reservation_updated_at', 6)->nullable();
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
