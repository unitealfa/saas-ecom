<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_promotions', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('sales_page_id')->nullable();
            $table->string('name');
            $table->unsignedTinyInteger('discount_type')->comment('DiscountTypeEnum: 1, 2, 3');
            $table->decimal('value', 14, 2);
            $table->integer('minimum_quantity');
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('ended_at', 6)->nullable();
            $table->integer('priority');
            $table->boolean('is_active');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_promotions');
    }
};
