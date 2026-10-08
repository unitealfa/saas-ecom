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
            $table->uuid('uuid');
            $table->foreignId('product_id')->nullable()->constrained('products', indexName: 'fk_free_shipping_rules_c3adad4f81')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('province_uuid')->nullable();
            $table->string('name');
            $table->unsignedTinyInteger('delivery_mode')->nullable()->comment('DeliveryModeEnum: 1, 2');
            $table->decimal('minimum_cart_amount')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->integer('priority');
            $table->boolean('is_active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id'], 'ix_free_shipping_rules_c3adad4f81');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_shipping_rules');
    }
};
