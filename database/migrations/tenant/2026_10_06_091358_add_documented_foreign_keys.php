<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add forward and circular foreign keys after every parent table and UNIQUE key exists.
     */
    public function up(): void
    {
        Schema::table('shop', function (Blueprint $table): void {
            $table->foreign(['logo_media_id'], 'fk_shop_bd28b60ea5')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['favicon_media_id'], 'fk_shop_8e7646ae6d')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('content_pages', function (Blueprint $table): void {
            $table->foreign(['product_id'], 'fk_content_pages_c3adad4f81')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->foreign(['visitor_id'], 'fk_product_reviews_f4e34ae2ec')->references(['id'])->on('visitors')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id'], 'fk_product_reviews_f63d0d3350')->references(['id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id', 'product_id'], 'fk_product_reviews_f503fc1f02')->references(['id', 'product_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('navigation_events', function (Blueprint $table): void {
            $table->foreign(['cart_id'], 'fk_navigation_events_eb4226caa5')->references(['id'])->on('carts')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign(['original_return_id'], 'fk_orders_c1f8c8f7a4')->references(['id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_incident_id'], 'fk_orders_7401f11da6')->references(['id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['current_revision_id'], 'fk_orders_ee67fd3519')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['confirmed_revision_id'], 'fk_orders_a114941ab3')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['current_revision_id', 'id'], 'fk_orders_79f7bc6c67')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['confirmed_revision_id', 'id'], 'fk_orders_430d4a293b')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_incident_id', 'original_order_id'], 'fk_orders_00146a1f46')->references(['id', 'order_id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_return_id', 'original_order_id'], 'fk_orders_c1dd89c96a')->references(['id', 'order_id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('order_revisions', function (Blueprint $table): void {
            $table->foreign(['free_shipping_rule_id'], 'fk_order_revisions_edc8ae80ff')->references(['id'])->on('free_shipping_rules')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreign(['return_item_id'], 'fk_stock_movements_859adac0ef')->references(['id'])->on('return_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['return_item_id', 'variant_id'], 'fk_stock_movements_a2c9ce4719')->references(['id', 'variant_id'])->on('return_items')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('order_returns', function (Blueprint $table): void {
            $table->foreign(['shipment_id'], 'fk_order_returns_9325aebf7e')->references(['id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipment_id', 'order_id', 'shipped_revision_id'], 'fk_order_returns_b5f82f238f')->references(['id', 'order_id', 'shipped_revision_id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('shipping_providers', function (Blueprint $table): void {
            $table->foreign(['carrier_account_id'], 'fk_shipping_providers_fd68d11272')->references(['id'])->on('carrier_accounts')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('shipping_rates', function (Blueprint $table): void {
            $table->foreign(['carrier_account_id'], 'fk_shipping_rates_fd68d11272')->references(['id'])->on('carrier_accounts')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('remittance_statements', function (Blueprint $table): void {
            $table->foreign(['carrier_remittance_batch_id'], 'fk_remittance_statements_2582706774')->references(['id'])->on('carrier_remittance_batches')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->foreign(['carrier_fee_id'], 'fk_carrier_settlement_lines_15d08bb160')->references(['id'])->on('carrier_fees')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['receivable_id'], 'fk_carrier_settlement_lines_989c7867e1')->references(['id'])->on('carrier_receivables')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['carrier_fee_id', 'shipment_id', 'provider_id'], 'fk_carrier_settlement_lines_94cc5a061f')->references(['id', 'shipment_id', 'provider_id'])->on('carrier_fees')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['receivable_id', 'provider_id'], 'fk_carrier_settlement_lines_228b02ea22')->references(['id', 'provider_id'])->on('carrier_receivables')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->foreign(['incident_id'], 'fk_customer_adjustments_04dbd90085')->references(['id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['credit_note_id'], 'fk_customer_adjustments_d61185a1fb')->references(['id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['incident_id', 'order_id'], 'fk_customer_adjustments_4e2d7fc88d')->references(['id', 'order_id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->foreign(['carrier_account_id'], 'fk_carrier_fees_fd68d11272')->references(['id'])->on('carrier_accounts')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreign(['sequence_id'], 'fk_invoices_ac063d431a')->references(['id'])->on('billing_rules')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['incident_id'], 'fk_invoices_04dbd90085')->references(['id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sequence_id', 'document_type', 'fiscal_year', 'sequence_record_type'], 'fk_invoices_b70ad3b9df')->references(['id', 'document_type', 'fiscal_year', 'record_type'])->on('billing_rules')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['incident_id', 'order_id'], 'fk_invoices_4e2d7fc88d')->references(['id', 'order_id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign('fk_invoices_ac063d431a');
            $table->dropForeign('fk_invoices_04dbd90085');
            $table->dropForeign('fk_invoices_b70ad3b9df');
            $table->dropForeign('fk_invoices_4e2d7fc88d');
        });

        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->dropForeign('fk_carrier_fees_fd68d11272');
        });

        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropForeign('fk_customer_adjustments_04dbd90085');
            $table->dropForeign('fk_customer_adjustments_d61185a1fb');
            $table->dropForeign('fk_customer_adjustments_4e2d7fc88d');
        });

        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropForeign('fk_carrier_settlement_lines_15d08bb160');
            $table->dropForeign('fk_carrier_settlement_lines_989c7867e1');
            $table->dropForeign('fk_carrier_settlement_lines_94cc5a061f');
            $table->dropForeign('fk_carrier_settlement_lines_228b02ea22');
        });

        Schema::table('remittance_statements', function (Blueprint $table): void {
            $table->dropForeign('fk_remittance_statements_2582706774');
        });

        Schema::table('shipping_rates', function (Blueprint $table): void {
            $table->dropForeign('fk_shipping_rates_fd68d11272');
        });

        Schema::table('shipping_providers', function (Blueprint $table): void {
            $table->dropForeign('fk_shipping_providers_fd68d11272');
        });

        Schema::table('order_returns', function (Blueprint $table): void {
            $table->dropForeign('fk_order_returns_9325aebf7e');
            $table->dropForeign('fk_order_returns_b5f82f238f');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign('fk_stock_movements_859adac0ef');
            $table->dropForeign('fk_stock_movements_a2c9ce4719');
        });

        Schema::table('order_revisions', function (Blueprint $table): void {
            $table->dropForeign('fk_order_revisions_edc8ae80ff');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign('fk_orders_c1f8c8f7a4');
            $table->dropForeign('fk_orders_7401f11da6');
            $table->dropForeign('fk_orders_ee67fd3519');
            $table->dropForeign('fk_orders_a114941ab3');
            $table->dropForeign('fk_orders_79f7bc6c67');
            $table->dropForeign('fk_orders_430d4a293b');
            $table->dropForeign('fk_orders_00146a1f46');
            $table->dropForeign('fk_orders_c1dd89c96a');
        });

        Schema::table('navigation_events', function (Blueprint $table): void {
            $table->dropForeign('fk_navigation_events_eb4226caa5');
        });

        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->dropForeign('fk_product_reviews_f4e34ae2ec');
            $table->dropForeign('fk_product_reviews_f63d0d3350');
            $table->dropForeign('fk_product_reviews_f503fc1f02');
        });

        Schema::table('content_pages', function (Blueprint $table): void {
            $table->dropForeign('fk_content_pages_c3adad4f81');
        });

        Schema::table('shop', function (Blueprint $table): void {
            $table->dropForeign('fk_shop_bd28b60ea5');
            $table->dropForeign('fk_shop_8e7646ae6d');
        });
    }
};
