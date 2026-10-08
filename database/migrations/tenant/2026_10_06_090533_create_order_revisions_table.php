<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (free_shipping_rule_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('order_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('order_id')->constrained('orders', indexName: 'fk_order_revisions_ca13a6b2c9')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('author_id')->nullable()->constrained('users', indexName: 'fk_order_revisions_378e66e226')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('free_shipping_rule_id')->nullable();
            $table->uuid('pickup_point_uuid')->nullable();
            $table->uuid('province_uuid');
            $table->uuid('municipality_uuid');
            $table->integer('revision_number');
            $table->char('currency');
            $table->char('country_code');
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
            $table->decimal('catalog_subtotal');
            $table->decimal('applied_subtotal');
            $table->decimal('customer_shipping_fee');
            $table->decimal('shipping_discount');
            $table->unsignedTinyInteger('shipping_charge_bearer')->comment('ShippingChargeBearerEnum: 1, 2, 3');
            $table->decimal('merchant_shipping_amount');
            $table->decimal('order_total');
            $table->decimal('return_cost_recovery_amount')->default(0);
            $table->text('return_cost_recovery_reason')->nullable();
            $table->decimal('amount_to_collect');
            $table->text('customer_note')->nullable();
            $table->string('sales_terms_version');
            $table->json('sales_terms_snapshot');
            $table->timestamp('created_at');

            $table->unique(['id', 'order_id'], 'uq_order_revisions_cc60f48a93');
            $table->unique(['id', 'order_id', 'delivery_mode'], 'uq_order_revisions_3ab12e7b89');
            $table->unique(['id', 'order_id', 'pickup_point_uuid'], 'uq_order_revisions_0fbac59544');
            $table->unique(['order_id', 'revision_number'], 'uq_order_revisions_67e1c1b351');
            $table->index(['author_id'], 'ix_order_revisions_378e66e226');
            $table->index(['free_shipping_rule_id'], 'ix_order_revisions_edc8ae80ff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_revisions');
    }
};
