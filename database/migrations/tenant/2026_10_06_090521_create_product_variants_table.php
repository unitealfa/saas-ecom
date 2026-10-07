<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('product_id');
            $table->string('label');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->char('combination_signature', 64)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->dateTime('used_at', 6)->nullable();
            $table->decimal('sale_price', 14, 2);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('previous_price', 14, 2)->nullable();
            $table->json('tax_configuration')->nullable();
            $table->integer('physical_stock');
            $table->integer('reserved_stock');
            $table->integer('quarantine_stock');
            $table->integer('low_stock_threshold');
            $table->decimal('weight_kg', 14, 3)->nullable();
            $table->decimal('length_cm', 14, 3)->nullable();
            $table->decimal('width_cm', 14, 3)->nullable();
            $table->decimal('height_cm', 14, 3)->nullable();
            $table->boolean('is_active');
            $table->integer('position');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
