<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_revisions', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->unsignedBigInteger('free_shipping_rule_id')->nullable();
            $table->char('pickup_point_uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable();
            $table->char('province_uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->char('municipality_uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->integer('revision_number');
            $table->char('currency', 3);
            $table->char('country_code', 2);
            $table->json('legal_seller_snapshot');
            $table->json('shipping_tax_snapshot');
            $table->text('reason')->nullable();
            $table->string('recipient_last_name');
            $table->string('recipient_first_name')->nullable();
            $table->string('phone');
            $table->string('secondary_phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address');
            $table->string('province_name');
            $table->string('municipality_name');
            $table->string('postal_code')->nullable();
            $table->unsignedTinyInteger('delivery_mode')->comment('DeliveryModeEnum: 1, 2');
            $table->json('pickup_point_snapshot')->nullable();
            $table->decimal('catalog_subtotal', 14, 2);
            $table->decimal('applied_subtotal', 14, 2);
            $table->decimal('customer_shipping_fee', 14, 2);
            $table->decimal('shipping_discount', 14, 2);
            $table->unsignedTinyInteger('shipping_charge_bearer')->comment('ShippingChargeBearerEnum: 1, 2, 3');
            $table->decimal('merchant_shipping_amount', 14, 2);
            $table->decimal('order_total', 14, 2);
            $table->decimal('return_cost_recovery_amount', 14, 2)->default(0);
            $table->text('return_cost_recovery_reason')->nullable();
            $table->decimal('amount_to_collect', 14, 2);
            $table->text('customer_note')->nullable();
            $table->string('sales_terms_version');
            $table->json('sales_terms_snapshot');
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_revisions');
    }
};
