<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop', function (Blueprint $table): void {
            $table->foreign(['logo_media_id'], 'fk_shop_bd28b60ea5')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['favicon_media_id'], 'fk_shop_8e7646ae6d')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('shop_addresses', function (Blueprint $table): void {
            $table->foreign(['shop_id'], 'fk_shop_addresses_176fd1ac1e')->references(['id'])->on('shop')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_address_id'], 'fk_shop_addresses_e08e4d9849')->references(['id'])->on('shop_addresses')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_address_id', 'shop_id', 'shop_address_type'], 'fk_shop_addresses_88543b6ab4')->references(['id', 'shop_id', 'record_type'])->on('shop_addresses')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('content_pages', function (Blueprint $table): void {
            $table->foreign(['product_id'], 'fk_content_pages_c3adad4f81')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->foreign(['created_by_id'], 'fk_media_6fb667974b')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->foreign(['parent_id'], 'fk_categories_7b54484fae')->references(['id'])->on('categories')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['media_id'], 'fk_categories_d3fc3e3e3a')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['parent_id', 'parent_record_type'], 'fk_categories_0f3b800172')->references(['id', 'record_type'])->on('categories')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreign(['category_id'], 'fk_products_4f0d62547a')->references(['id'])->on('categories')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['category_id', 'category_record_type'], 'fk_products_0846ff10b7')->references(['id', 'record_type'])->on('categories')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->foreign(['product_id'], 'fk_product_variants_c3adad4f81')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('product_options', function (Blueprint $table): void {
            $table->foreign(['product_id'], 'fk_product_options_c3adad4f81')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['parent_id'], 'fk_product_options_7b54484fae')->references(['id'])->on('product_options')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['parent_id', 'product_id', 'parent_record_type'], 'fk_product_options_12a5993334')->references(['id', 'product_id', 'record_type'])->on('product_options')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('variant_option_values', function (Blueprint $table): void {
            $table->foreign(['product_id'], 'fk_variant_option_values_c3adad4f81')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['variant_id'], 'fk_variant_option_values_14f215ed6d')->references(['id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['option_id'], 'fk_variant_option_values_5fb9f6d52f')->references(['id'])->on('product_options')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['value_id'], 'fk_variant_option_values_9714733197')->references(['id'])->on('product_options')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['variant_id', 'product_id'], 'fk_variant_option_values_29b92b5d93')->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['option_id', 'product_id', 'option_record_type'], 'fk_variant_option_values_537c9500da')->references(['id', 'product_id', 'record_type'])->on('product_options')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['value_id', 'option_id', 'product_id', 'value_record_type'], 'fk_variant_option_values_ef33e1fc9b')->references(['id', 'parent_id', 'product_id', 'record_type'])->on('product_options')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('product_tags', function (Blueprint $table): void {
            $table->foreign(['product_id'], 'fk_product_tags_c3adad4f81')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['tag_id'], 'fk_product_tags_6040e81886')->references(['id'])->on('categories')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['tag_id', 'tag_record_type'], 'fk_product_tags_e950e25458')->references(['id', 'record_type'])->on('categories')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('product_promotions', function (Blueprint $table): void {
            $table->foreign(['product_id'], 'fk_product_promotions_c3adad4f81')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['variant_id'], 'fk_product_promotions_14f215ed6d')->references(['id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sales_page_id'], 'fk_product_promotions_b31f7a60fc')->references(['id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['variant_id', 'product_id'], 'fk_product_promotions_29b92b5d93')->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sales_page_id', 'product_id'], 'fk_product_promotions_59bc361caa')->references(['id', 'product_id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->foreign(['product_id'], 'fk_product_reviews_c3adad4f81')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['visitor_id'], 'fk_product_reviews_f4e34ae2ec')->references(['id'])->on('visitors')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id'], 'fk_product_reviews_f63d0d3350')->references(['id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['moderated_by_id'], 'fk_product_reviews_9ebbb59674')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id', 'product_id'], 'fk_product_reviews_f503fc1f02')->references(['id', 'product_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('visit_sessions', function (Blueprint $table): void {
            $table->foreign(['visitor_id'], 'fk_visit_sessions_f4e34ae2ec')->references(['id'])->on('visitors')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('navigation_events', function (Blueprint $table): void {
            $table->foreign(['session_id'], 'fk_navigation_events_5c3a09bf22')->references(['id'])->on('visit_sessions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['product_id'], 'fk_navigation_events_c3adad4f81')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['variant_id'], 'fk_navigation_events_14f215ed6d')->references(['id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sales_page_id'], 'fk_navigation_events_b31f7a60fc')->references(['id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['content_page_id'], 'fk_navigation_events_fe9e538371')->references(['id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['cart_id'], 'fk_navigation_events_eb4226caa5')->references(['id'])->on('carts')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sales_page_id', 'product_id'], 'fk_navigation_events_59bc361caa')->references(['id', 'product_id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sales_page_id', 'sales_page_kind'], 'fk_navigation_events_09613a1a6f')->references(['id', 'page_kind'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['content_page_id', 'content_page_kind'], 'fk_navigation_events_2e86a84456')->references(['id', 'page_kind'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->foreign(['visitor_id'], 'fk_carts_f4e34ae2ec')->references(['id'])->on('visitors')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->foreign(['cart_id'], 'fk_cart_items_eb4226caa5')->references(['id'])->on('carts')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['variant_id'], 'fk_cart_items_14f215ed6d')->references(['id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['product_id'], 'fk_cart_items_c3adad4f81')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sales_page_id'], 'fk_cart_items_b31f7a60fc')->references(['id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['variant_id', 'product_id'], 'fk_cart_items_29b92b5d93')->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sales_page_id', 'product_id'], 'fk_cart_items_59bc361caa')->references(['id', 'product_id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign(['visitor_id'], 'fk_orders_f4e34ae2ec')->references(['id'])->on('visitors')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['cart_id'], 'fk_orders_eb4226caa5')->references(['id'])->on('carts')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_session_id'], 'fk_orders_2a9ca5a1fd')->references(['id'])->on('visit_sessions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_sales_page_id'], 'fk_orders_e50ff0c50c')->references(['id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_return_id'], 'fk_orders_c1f8c8f7a4')->references(['id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_order_id'], 'fk_orders_b41c795bd0')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_incident_id'], 'fk_orders_7401f11da6')->references(['id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['current_revision_id'], 'fk_orders_ee67fd3519')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['confirmed_revision_id'], 'fk_orders_a114941ab3')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['confirmation_owner_id'], 'fk_orders_0d5c9ac004')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['operationally_confirmed_by_id'], 'fk_orders_7f60a79ecc')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['current_revision_id', 'id'], 'fk_orders_79f7bc6c67')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['confirmed_revision_id', 'id'], 'fk_orders_430d4a293b')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_incident_id', 'original_order_id'], 'fk_orders_00146a1f46')->references(['id', 'order_id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_return_id', 'original_order_id'], 'fk_orders_c1dd89c96a')->references(['id', 'order_id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_sales_page_id', 'original_sales_page_kind'], 'fk_orders_122c5334b9')->references(['id', 'page_kind'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('order_revisions', function (Blueprint $table): void {
            $table->foreign(['order_id'], 'fk_order_revisions_ca13a6b2c9')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['author_id'], 'fk_order_revisions_378e66e226')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['free_shipping_rule_id'], 'fk_order_revisions_edc8ae80ff')->references(['id'])->on('free_shipping_rules')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreign(['revision_id'], 'fk_order_items_e22455d8ca')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['variant_id'], 'fk_order_items_14f215ed6d')->references(['id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['product_id'], 'fk_order_items_c3adad4f81')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['promotion_id'], 'fk_order_items_baeda0db73')->references(['id'])->on('product_promotions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sales_page_id'], 'fk_order_items_b31f7a60fc')->references(['id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['variant_id', 'product_id'], 'fk_order_items_29b92b5d93')->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sales_page_id', 'product_id'], 'fk_order_items_59bc361caa')->references(['id', 'product_id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('order_history', function (Blueprint $table): void {
            $table->foreign(['order_id'], 'fk_order_history_ca13a6b2c9')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['previous_revision_id'], 'fk_order_history_cb5d6a7448')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['next_revision_id'], 'fk_order_history_4fbb76793e')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['actor_id'], 'fk_order_history_fe4b4e1602')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['previous_revision_id', 'order_id'], 'fk_order_history_8cd5f69ee7')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['next_revision_id', 'order_id'], 'fk_order_history_3790f81ee9')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreign(['variant_id'], 'fk_stock_movements_14f215ed6d')->references(['id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id'], 'fk_stock_movements_f63d0d3350')->references(['id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['return_item_id'], 'fk_stock_movements_859adac0ef')->references(['id'])->on('return_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['actor_id'], 'fk_stock_movements_fe4b4e1602')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id'], 'fk_stock_movements_d046da8d50')->references(['id'])->on('stock_movements')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id', 'variant_id'], 'fk_stock_movements_36a9296d11')->references(['id', 'variant_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['return_item_id', 'variant_id'], 'fk_stock_movements_a2c9ce4719')->references(['id', 'variant_id'])->on('return_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'variant_id'], 'fk_stock_movements_e6592dc642')->references(['id', 'variant_id'])->on('stock_movements')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('order_returns', function (Blueprint $table): void {
            $table->foreign(['shipment_id'], 'fk_order_returns_9325aebf7e')->references(['id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_id'], 'fk_order_returns_ca13a6b2c9')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipped_revision_id'], 'fk_order_returns_f48a5da11a')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['received_by_id'], 'fk_order_returns_9cd65364a6')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipment_id', 'order_id', 'shipped_revision_id'], 'fk_order_returns_b5f82f238f')->references(['id', 'order_id', 'shipped_revision_id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('return_items', function (Blueprint $table): void {
            $table->foreign(['return_id'], 'fk_return_items_4ee7679ca5')->references(['id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id'], 'fk_return_items_f63d0d3350')->references(['id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipped_revision_id'], 'fk_return_items_f48a5da11a')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['variant_id'], 'fk_return_items_14f215ed6d')->references(['id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['inspected_by_id'], 'fk_return_items_e8b4e1ba43')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['return_id', 'shipped_revision_id'], 'fk_return_items_aaf597818c')->references(['id', 'shipped_revision_id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id', 'shipped_revision_id'], 'fk_return_items_14ba853e98')->references(['id', 'revision_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id', 'variant_id'], 'fk_return_items_36a9296d11')->references(['id', 'variant_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('shipping_providers', function (Blueprint $table): void {
            $table->foreign(['user_id'], 'fk_shipping_providers_f89d6b6960')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['carrier_account_id'], 'fk_shipping_providers_fd68d11272')->references(['id'])->on('carrier_accounts')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('shipping_rates', function (Blueprint $table): void {
            $table->foreign(['provider_id'], 'fk_shipping_rates_b3c7cddea3')->references(['id'])->on('shipping_providers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['carrier_account_id'], 'fk_shipping_rates_fd68d11272')->references(['id'])->on('carrier_accounts')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['created_by_id'], 'fk_shipping_rates_6fb667974b')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('free_shipping_rules', function (Blueprint $table): void {
            $table->foreign(['product_id'], 'fk_free_shipping_rules_c3adad4f81')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->foreign(['order_id'], 'fk_shipments_ca13a6b2c9')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipped_revision_id'], 'fk_shipments_f48a5da11a')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['provider_id'], 'fk_shipments_b3c7cddea3')->references(['id'])->on('shipping_providers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['label_media_id'], 'fk_shipments_b3b4e83a73')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['assigned_by_id'], 'fk_shipments_bc15dd68ce')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipped_revision_id', 'order_id'], 'fk_shipments_02c54136b3')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipped_revision_id', 'order_id', 'delivery_mode'], 'fk_shipments_25270a5a20')->references(['id', 'order_id', 'delivery_mode'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipped_revision_id', 'order_id', 'pickup_point_uuid'], 'fk_shipments_d69c160f70')->references(['id', 'order_id', 'pickup_point_uuid'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('shipment_events', function (Blueprint $table): void {
            $table->foreign(['shipment_id'], 'fk_shipment_events_9325aebf7e')->references(['id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['actor_id'], 'fk_shipment_events_fe4b4e1602')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->foreign(['provider_id'], 'fk_carrier_operations_b3c7cddea3')->references(['id'])->on('shipping_providers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipment_id'], 'fk_carrier_operations_9325aebf7e')->references(['id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_id'], 'fk_carrier_operations_ca13a6b2c9')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['return_id'], 'fk_carrier_operations_4ee7679ca5')->references(['id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['revision_id'], 'fk_carrier_operations_e22455d8ca')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['superseded_by_operation_id'], 'fk_carrier_operations_41c5e1821d')->references(['id'])->on('carrier_operations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['triggered_by_id'], 'fk_carrier_operations_176faee833')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['revision_id', 'order_id'], 'fk_carrier_operations_ff6828c755')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipment_id', 'order_id'], 'fk_carrier_operations_4066108b3c')->references(['id', 'order_id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('carrier_operation_attempts', function (Blueprint $table): void {
            $table->foreign(['operation_id'], 'fk_carrier_operation_attempts_9a9856b253')->references(['id'])->on('carrier_operations')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('collections', function (Blueprint $table): void {
            $table->foreign(['shipment_id'], 'fk_collections_9325aebf7e')->references(['id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('remittance_statements', function (Blueprint $table): void {
            $table->foreign(['provider_id'], 'fk_remittance_statements_b3c7cddea3')->references(['id'])->on('shipping_providers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['carrier_remittance_batch_id'], 'fk_remittance_statements_2582706774')->references(['id'])->on('carrier_remittance_batches')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['validated_by_id'], 'fk_remittance_statements_6a16de7f77')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['proof_media_id'], 'fk_remittance_statements_6aed88530f')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id'], 'fk_remittance_statements_d046da8d50')->references(['id'])->on('remittance_statements')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->foreign(['provider_id'], 'fk_carrier_settlement_lines_b3c7cddea3')->references(['id'])->on('shipping_providers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['remittance_statement_id'], 'fk_carrier_settlement_lines_3637755e4b')->references(['id'])->on('remittance_statements')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['collection_id'], 'fk_carrier_settlement_lines_da719fe1c4')->references(['id'])->on('collections')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['carrier_fee_id'], 'fk_carrier_settlement_lines_15d08bb160')->references(['id'])->on('carrier_fees')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['receivable_id'], 'fk_carrier_settlement_lines_989c7867e1')->references(['id'])->on('carrier_receivables')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipment_id'], 'fk_carrier_settlement_lines_9325aebf7e')->references(['id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['replacement_order_id'], 'fk_carrier_settlement_lines_1e4156bc0b')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['proof_media_id'], 'fk_carrier_settlement_lines_6aed88530f')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id'], 'fk_carrier_settlement_lines_d046da8d50')->references(['id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id'], 'fk_carrier_settlement_lines_d0f90c8322')->references(['id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['remittance_statement_id', 'provider_id'], 'fk_carrier_settlement_lines_3988575ec8')->references(['id', 'provider_id'])->on('remittance_statements')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipment_id', 'provider_id'], 'fk_carrier_settlement_lines_c98047af1c')->references(['id', 'provider_id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['collection_id', 'shipment_id'], 'fk_carrier_settlement_lines_805dec760f')->references(['id', 'shipment_id'])->on('collections')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['carrier_fee_id', 'shipment_id', 'provider_id'], 'fk_carrier_settlement_lines_94cc5a061f')->references(['id', 'shipment_id', 'provider_id'])->on('carrier_fees')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['receivable_id', 'provider_id'], 'fk_carrier_settlement_lines_228b02ea22')->references(['id', 'provider_id'])->on('carrier_receivables')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'record_type', 'provider_id'], 'fk_carrier_settlement_lines_4570412ab1')->references(['id', 'record_type', 'provider_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'record_type', 'provider_id', 'collection_id'], 'fk_carrier_settlement_lines_925c950e83')->references(['id', 'record_type', 'provider_id', 'collection_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'record_type', 'provider_id', 'carrier_fee_id'], 'fk_carrier_settlement_lines_2223ed5210')->references(['id', 'record_type', 'provider_id', 'carrier_fee_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'record_type', 'provider_id', 'receivable_id'], 'fk_carrier_settlement_lines_9860c0e09b')->references(['id', 'record_type', 'provider_id', 'receivable_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'record_type', 'provider_id', 'shipment_id'], 'fk_carrier_settlement_lines_857b657a79')->references(['id', 'record_type', 'provider_id', 'shipment_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'record_type', 'provider_id'], 'fk_carrier_settlement_lines_0c47191ede')->references(['id', 'record_type', 'provider_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'record_type', 'provider_id', 'collection_id'], 'fk_carrier_settlement_lines_d8429d5699')->references(['id', 'record_type', 'provider_id', 'collection_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'record_type', 'provider_id', 'carrier_fee_id'], 'fk_carrier_settlement_lines_8233cbb96c')->references(['id', 'record_type', 'provider_id', 'carrier_fee_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'record_type', 'provider_id', 'receivable_id'], 'fk_carrier_settlement_lines_ed98a017a8')->references(['id', 'record_type', 'provider_id', 'receivable_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'record_type', 'provider_id', 'shipment_id'], 'fk_carrier_settlement_lines_70de42edf6')->references(['id', 'record_type', 'provider_id', 'shipment_id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreign(['product_id'], 'fk_expenses_c3adad4f81')->references(['id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_id'], 'fk_expenses_ca13a6b2c9')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipment_id'], 'fk_expenses_9325aebf7e')->references(['id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['return_id'], 'fk_expenses_4ee7679ca5')->references(['id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['proof_media_id'], 'fk_expenses_6aed88530f')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['author_id'], 'fk_expenses_378e66e226')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id'], 'fk_expenses_d046da8d50')->references(['id'])->on('expenses')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id'], 'fk_expenses_d0f90c8322')->references(['id'])->on('expenses')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->foreign(['order_id'], 'fk_customer_adjustments_ca13a6b2c9')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['return_id'], 'fk_customer_adjustments_4ee7679ca5')->references(['id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['incident_id'], 'fk_customer_adjustments_04dbd90085')->references(['id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['credit_note_id'], 'fk_customer_adjustments_d61185a1fb')->references(['id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['validated_by_id'], 'fk_customer_adjustments_6a16de7f77')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['proof_media_id'], 'fk_customer_adjustments_6aed88530f')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id'], 'fk_customer_adjustments_d046da8d50')->references(['id'])->on('customer_adjustments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id'], 'fk_customer_adjustments_d0f90c8322')->references(['id'])->on('customer_adjustments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['incident_id', 'order_id'], 'fk_customer_adjustments_4e2d7fc88d')->references(['id', 'order_id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'order_id', 'incident_id'], 'fk_customer_adjustments_1220d124fe')->references(['id', 'order_id', 'incident_id'])->on('customer_adjustments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'order_id', 'incident_id'], 'fk_customer_adjustments_1ebd6bb067')->references(['id', 'order_id', 'incident_id'])->on('customer_adjustments')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('order_documents', function (Blueprint $table): void {
            $table->foreign(['order_id'], 'fk_order_documents_ca13a6b2c9')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['revision_id'], 'fk_order_documents_e22455d8ca')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['media_id'], 'fk_order_documents_d3fc3e3e3a')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['generated_by_id'], 'fk_order_documents_dceaab373c')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['revision_id', 'order_id'], 'fk_order_documents_ff6828c755')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->foreign(['shipment_id'], 'fk_carrier_fees_9325aebf7e')->references(['id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['provider_id'], 'fk_carrier_fees_b3c7cddea3')->references(['id'])->on('shipping_providers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['return_id'], 'fk_carrier_fees_4ee7679ca5')->references(['id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['carrier_account_id'], 'fk_carrier_fees_fd68d11272')->references(['id'])->on('carrier_accounts')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['source_rate_id'], 'fk_carrier_fees_8c8e195cf9')->references(['id'])->on('shipping_rates')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['proof_media_id'], 'fk_carrier_fees_6aed88530f')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id'], 'fk_carrier_fees_d046da8d50')->references(['id'])->on('carrier_fees')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id'], 'fk_carrier_fees_d0f90c8322')->references(['id'])->on('carrier_fees')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['source_rate_id', 'carrier_account_id', 'source_rate_record_type'], 'fk_carrier_fees_3d038b8aa4')->references(['id', 'carrier_account_id', 'record_type'])->on('shipping_rates')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipment_id', 'provider_id'], 'fk_carrier_fees_c98047af1c')->references(['id', 'provider_id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['return_id', 'shipment_id'], 'fk_carrier_fees_a663f454eb')->references(['id', 'shipment_id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('carrier_receivables', function (Blueprint $table): void {
            $table->foreign(['provider_id'], 'fk_carrier_receivables_b3c7cddea3')->references(['id'])->on('shipping_providers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['carrier_fee_id'], 'fk_carrier_receivables_15d08bb160')->references(['id'])->on('carrier_fees')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_fee_payment_id'], 'fk_carrier_receivables_2508791d40')->references(['id'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id'], 'fk_carrier_receivables_d046da8d50')->references(['id'])->on('carrier_receivables')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_fee_payment_id', 'provider_id', 'original_fee_payment_record_type'], 'fk_carrier_receivables_c187825194')->references(['id', 'provider_id', 'record_type'])->on('carrier_settlement_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'provider_id'], 'fk_carrier_receivables_82e929ff12')->references(['id', 'provider_id'])->on('carrier_receivables')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('collection_entries', function (Blueprint $table): void {
            $table->foreign(['collection_id'], 'fk_collection_entries_da719fe1c4')->references(['id'])->on('collections')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['verified_by_id'], 'fk_collection_entries_b8ab8c1e46')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['proof_media_id'], 'fk_collection_entries_6aed88530f')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id'], 'fk_collection_entries_d046da8d50')->references(['id'])->on('collection_entries')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id'], 'fk_collection_entries_d0f90c8322')->references(['id'])->on('collection_entries')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'collection_id'], 'fk_collection_entries_0bea1a3527')->references(['id', 'collection_id'])->on('collection_entries')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'collection_id'], 'fk_collection_entries_30763314bb')->references(['id', 'collection_id'])->on('collection_entries')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreign(['order_id'], 'fk_invoices_ca13a6b2c9')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['revision_id'], 'fk_invoices_e22455d8ca')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_invoice_id'], 'fk_invoices_ec080ce729')->references(['id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sequence_id'], 'fk_invoices_ac063d431a')->references(['id'])->on('billing_rules')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['media_id'], 'fk_invoices_d3fc3e3e3a')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['issued_by_id'], 'fk_invoices_35693fc42b')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['incident_id'], 'fk_invoices_04dbd90085')->references(['id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sequence_id', 'document_type', 'fiscal_year', 'sequence_record_type'], 'fk_invoices_b70ad3b9df')->references(['id', 'document_type', 'fiscal_year', 'record_type'])->on('billing_rules')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['revision_id', 'order_id'], 'fk_invoices_ff6828c755')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_invoice_id', 'order_id'], 'fk_invoices_3a27fee5c5')->references(['id', 'order_id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['incident_id', 'order_id'], 'fk_invoices_4e2d7fc88d')->references(['id', 'order_id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('order_incidents', function (Blueprint $table): void {
            $table->foreign(['order_id'], 'fk_order_incidents_ca13a6b2c9')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipment_id'], 'fk_order_incidents_9325aebf7e')->references(['id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipped_revision_id'], 'fk_order_incidents_f48a5da11a')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id'], 'fk_order_incidents_f63d0d3350')->references(['id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['return_id'], 'fk_order_incidents_4ee7679ca5')->references(['id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['opened_by_id'], 'fk_order_incidents_db5c37fda7')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['validated_by_id'], 'fk_order_incidents_6a16de7f77')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shipment_id', 'order_id', 'shipped_revision_id'], 'fk_order_incidents_b5f82f238f')->references(['id', 'order_id', 'shipped_revision_id'])->on('shipments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id', 'shipped_revision_id'], 'fk_order_incidents_14ba853e98')->references(['id', 'revision_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['return_id', 'shipment_id'], 'fk_order_incidents_a663f454eb')->references(['id', 'shipment_id'])->on('order_returns')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('order_incident_details', function (Blueprint $table): void {
            $table->foreign(['incident_id'], 'fk_order_incident_details_04dbd90085')->references(['id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['author_id'], 'fk_order_incident_details_378e66e226')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('billing_rules', function (Blueprint $table): void {
            $table->foreign(['validated_by_id'], 'fk_billing_rules_6a16de7f77')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('sales_terms_acceptances', function (Blueprint $table): void {
            $table->foreign(['order_id'], 'fk_sales_terms_acceptances_ca13a6b2c9')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['revision_id'], 'fk_sales_terms_acceptances_e22455d8ca')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['revision_id', 'order_id'], 'fk_sales_terms_acceptances_ff6828c755')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('billing_obligations', function (Blueprint $table): void {
            $table->foreign(['order_id'], 'fk_billing_obligations_ca13a6b2c9')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['revision_id'], 'fk_billing_obligations_e22455d8ca')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['billing_rule_id'], 'fk_billing_obligations_7de4923723')->references(['id'])->on('billing_rules')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_invoice_id'], 'fk_billing_obligations_ec080ce729')->references(['id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['invoice_id'], 'fk_billing_obligations_16a54288bc')->references(['id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['billing_rule_id', 'billing_rule_record_type'], 'fk_billing_obligations_1bae90140d')->references(['id', 'record_type'])->on('billing_rules')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['revision_id', 'order_id'], 'fk_billing_obligations_ff6828c755')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['invoice_id', 'order_id', 'revision_id', 'document_type'], 'fk_billing_obligations_69ee8cdbb1')->references(['id', 'order_id', 'revision_id', 'document_type'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['invoice_id', 'order_id', 'revision_id', 'document_type', 'original_invoice_id'], 'fk_billing_obligations_0291334460')->references(['id', 'order_id', 'revision_id', 'document_type', 'original_invoice_id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('commercial_corrections', function (Blueprint $table): void {
            $table->foreign(['order_id'], 'fk_commercial_corrections_ca13a6b2c9')->references(['id'])->on('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['source_revision_id'], 'fk_commercial_corrections_d329d3e500')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['incident_id'], 'fk_commercial_corrections_04dbd90085')->references(['id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id'], 'fk_commercial_corrections_d0f90c8322')->references(['id'])->on('commercial_corrections')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['actor_id'], 'fk_commercial_corrections_fe4b4e1602')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_of_id', 'order_id', 'source_revision_id'], 'fk_commercial_corrections_8834292179')->references(['id', 'order_id', 'source_revision_id'])->on('commercial_corrections')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['source_revision_id', 'order_id'], 'fk_commercial_corrections_a5c7e0ed56')->references(['id', 'order_id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['incident_id', 'order_id'], 'fk_commercial_corrections_4e2d7fc88d')->references(['id', 'order_id'])->on('order_incidents')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('commercial_correction_lines', function (Blueprint $table): void {
            $table->foreign(['correction_id'], 'fk_commercial_correction_lines_b86c400c6c')->references(['id'])->on('commercial_corrections')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['source_revision_id'], 'fk_commercial_correction_lines_d329d3e500')->references(['id'])->on('order_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id'], 'fk_commercial_correction_lines_f63d0d3350')->references(['id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['correction_id', 'source_revision_id'], 'fk_commercial_correction_lines_a8c9aa0cb7')->references(['id', 'source_revision_id'])->on('commercial_corrections')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['order_item_id', 'source_revision_id'], 'fk_commercial_correction_lines_37c2fc4d66')->references(['id', 'revision_id'])->on('order_items')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('role_has_permissions', function (Blueprint $table): void {
            $table->foreign(['permission_id'], 'fk_role_has_permissions_50d0bf2736')->references(['id'])->on('permissions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['role_id'], 'fk_role_has_permissions_26525afb8b')->references(['id'])->on('roles')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('model_has_roles', function (Blueprint $table): void {
            $table->foreign(['role_id'], 'fk_model_has_roles_26525afb8b')->references(['id'])->on('roles')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('model_has_permissions', function (Blueprint $table): void {
            $table->foreign(['permission_id'], 'fk_model_has_permissions_50d0bf2736')->references(['id'])->on('permissions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('team_invitations', function (Blueprint $table): void {
            $table->foreign(['initial_role_id'], 'fk_team_invitations_d5576bc50f')->references(['id'])->on('roles')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['invited_by_id'], 'fk_team_invitations_e3979f2144')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('carrier_accounts', function (Blueprint $table): void {
            $table->foreign(['created_by_id'], 'fk_carrier_accounts_6fb667974b')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('carrier_remittance_batches', function (Blueprint $table): void {
            $table->foreign(['carrier_account_id'], 'fk_carrier_remittance_batches_fd68d11272')->references(['id'])->on('carrier_accounts')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['proof_media_id'], 'fk_carrier_remittance_batches_6aed88530f')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id'], 'fk_carrier_remittance_batches_d046da8d50')->references(['id'])->on('carrier_remittance_batches')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['validated_by_id'], 'fk_carrier_remittance_batches_6a16de7f77')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'carrier_account_id'], 'fk_carrier_remittance_batches_5f7d97d0bf')->references(['id', 'carrier_account_id'])->on('carrier_remittance_batches')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('carrier_remittance_batches', function (Blueprint $table): void {
            $table->dropForeign('fk_carrier_remittance_batches_fd68d11272');
            $table->dropForeign('fk_carrier_remittance_batches_6aed88530f');
            $table->dropForeign('fk_carrier_remittance_batches_d046da8d50');
            $table->dropForeign('fk_carrier_remittance_batches_6a16de7f77');
            $table->dropForeign('fk_carrier_remittance_batches_5f7d97d0bf');
        });

        Schema::table('carrier_accounts', function (Blueprint $table): void {
            $table->dropForeign('fk_carrier_accounts_6fb667974b');
        });

        Schema::table('team_invitations', function (Blueprint $table): void {
            $table->dropForeign('fk_team_invitations_d5576bc50f');
            $table->dropForeign('fk_team_invitations_e3979f2144');
        });

        Schema::table('model_has_permissions', function (Blueprint $table): void {
            $table->dropForeign('fk_model_has_permissions_50d0bf2736');
        });

        Schema::table('model_has_roles', function (Blueprint $table): void {
            $table->dropForeign('fk_model_has_roles_26525afb8b');
        });

        Schema::table('role_has_permissions', function (Blueprint $table): void {
            $table->dropForeign('fk_role_has_permissions_50d0bf2736');
            $table->dropForeign('fk_role_has_permissions_26525afb8b');
        });

        Schema::table('commercial_correction_lines', function (Blueprint $table): void {
            $table->dropForeign('fk_commercial_correction_lines_b86c400c6c');
            $table->dropForeign('fk_commercial_correction_lines_d329d3e500');
            $table->dropForeign('fk_commercial_correction_lines_f63d0d3350');
            $table->dropForeign('fk_commercial_correction_lines_a8c9aa0cb7');
            $table->dropForeign('fk_commercial_correction_lines_37c2fc4d66');
        });

        Schema::table('commercial_corrections', function (Blueprint $table): void {
            $table->dropForeign('fk_commercial_corrections_ca13a6b2c9');
            $table->dropForeign('fk_commercial_corrections_d329d3e500');
            $table->dropForeign('fk_commercial_corrections_04dbd90085');
            $table->dropForeign('fk_commercial_corrections_d0f90c8322');
            $table->dropForeign('fk_commercial_corrections_fe4b4e1602');
            $table->dropForeign('fk_commercial_corrections_8834292179');
            $table->dropForeign('fk_commercial_corrections_a5c7e0ed56');
            $table->dropForeign('fk_commercial_corrections_4e2d7fc88d');
        });

        Schema::table('billing_obligations', function (Blueprint $table): void {
            $table->dropForeign('fk_billing_obligations_ca13a6b2c9');
            $table->dropForeign('fk_billing_obligations_e22455d8ca');
            $table->dropForeign('fk_billing_obligations_7de4923723');
            $table->dropForeign('fk_billing_obligations_ec080ce729');
            $table->dropForeign('fk_billing_obligations_16a54288bc');
            $table->dropForeign('fk_billing_obligations_1bae90140d');
            $table->dropForeign('fk_billing_obligations_ff6828c755');
            $table->dropForeign('fk_billing_obligations_69ee8cdbb1');
            $table->dropForeign('fk_billing_obligations_0291334460');
        });

        Schema::table('sales_terms_acceptances', function (Blueprint $table): void {
            $table->dropForeign('fk_sales_terms_acceptances_ca13a6b2c9');
            $table->dropForeign('fk_sales_terms_acceptances_e22455d8ca');
            $table->dropForeign('fk_sales_terms_acceptances_ff6828c755');
        });

        Schema::table('billing_rules', function (Blueprint $table): void {
            $table->dropForeign('fk_billing_rules_6a16de7f77');
        });

        Schema::table('order_incident_details', function (Blueprint $table): void {
            $table->dropForeign('fk_order_incident_details_04dbd90085');
            $table->dropForeign('fk_order_incident_details_378e66e226');
        });

        Schema::table('order_incidents', function (Blueprint $table): void {
            $table->dropForeign('fk_order_incidents_ca13a6b2c9');
            $table->dropForeign('fk_order_incidents_9325aebf7e');
            $table->dropForeign('fk_order_incidents_f48a5da11a');
            $table->dropForeign('fk_order_incidents_f63d0d3350');
            $table->dropForeign('fk_order_incidents_4ee7679ca5');
            $table->dropForeign('fk_order_incidents_db5c37fda7');
            $table->dropForeign('fk_order_incidents_6a16de7f77');
            $table->dropForeign('fk_order_incidents_b5f82f238f');
            $table->dropForeign('fk_order_incidents_14ba853e98');
            $table->dropForeign('fk_order_incidents_a663f454eb');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign('fk_invoices_ca13a6b2c9');
            $table->dropForeign('fk_invoices_e22455d8ca');
            $table->dropForeign('fk_invoices_ec080ce729');
            $table->dropForeign('fk_invoices_ac063d431a');
            $table->dropForeign('fk_invoices_d3fc3e3e3a');
            $table->dropForeign('fk_invoices_35693fc42b');
            $table->dropForeign('fk_invoices_04dbd90085');
            $table->dropForeign('fk_invoices_b70ad3b9df');
            $table->dropForeign('fk_invoices_ff6828c755');
            $table->dropForeign('fk_invoices_3a27fee5c5');
            $table->dropForeign('fk_invoices_4e2d7fc88d');
        });

        Schema::table('collection_entries', function (Blueprint $table): void {
            $table->dropForeign('fk_collection_entries_da719fe1c4');
            $table->dropForeign('fk_collection_entries_b8ab8c1e46');
            $table->dropForeign('fk_collection_entries_6aed88530f');
            $table->dropForeign('fk_collection_entries_d046da8d50');
            $table->dropForeign('fk_collection_entries_d0f90c8322');
            $table->dropForeign('fk_collection_entries_0bea1a3527');
            $table->dropForeign('fk_collection_entries_30763314bb');
        });

        Schema::table('carrier_receivables', function (Blueprint $table): void {
            $table->dropForeign('fk_carrier_receivables_b3c7cddea3');
            $table->dropForeign('fk_carrier_receivables_15d08bb160');
            $table->dropForeign('fk_carrier_receivables_2508791d40');
            $table->dropForeign('fk_carrier_receivables_d046da8d50');
            $table->dropForeign('fk_carrier_receivables_c187825194');
            $table->dropForeign('fk_carrier_receivables_82e929ff12');
        });

        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->dropForeign('fk_carrier_fees_9325aebf7e');
            $table->dropForeign('fk_carrier_fees_b3c7cddea3');
            $table->dropForeign('fk_carrier_fees_4ee7679ca5');
            $table->dropForeign('fk_carrier_fees_fd68d11272');
            $table->dropForeign('fk_carrier_fees_8c8e195cf9');
            $table->dropForeign('fk_carrier_fees_6aed88530f');
            $table->dropForeign('fk_carrier_fees_d046da8d50');
            $table->dropForeign('fk_carrier_fees_d0f90c8322');
            $table->dropForeign('fk_carrier_fees_3d038b8aa4');
            $table->dropForeign('fk_carrier_fees_c98047af1c');
            $table->dropForeign('fk_carrier_fees_a663f454eb');
        });

        Schema::table('order_documents', function (Blueprint $table): void {
            $table->dropForeign('fk_order_documents_ca13a6b2c9');
            $table->dropForeign('fk_order_documents_e22455d8ca');
            $table->dropForeign('fk_order_documents_d3fc3e3e3a');
            $table->dropForeign('fk_order_documents_dceaab373c');
            $table->dropForeign('fk_order_documents_ff6828c755');
        });

        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropForeign('fk_customer_adjustments_ca13a6b2c9');
            $table->dropForeign('fk_customer_adjustments_4ee7679ca5');
            $table->dropForeign('fk_customer_adjustments_04dbd90085');
            $table->dropForeign('fk_customer_adjustments_d61185a1fb');
            $table->dropForeign('fk_customer_adjustments_6a16de7f77');
            $table->dropForeign('fk_customer_adjustments_6aed88530f');
            $table->dropForeign('fk_customer_adjustments_d046da8d50');
            $table->dropForeign('fk_customer_adjustments_d0f90c8322');
            $table->dropForeign('fk_customer_adjustments_4e2d7fc88d');
            $table->dropForeign('fk_customer_adjustments_1220d124fe');
            $table->dropForeign('fk_customer_adjustments_1ebd6bb067');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropForeign('fk_expenses_c3adad4f81');
            $table->dropForeign('fk_expenses_ca13a6b2c9');
            $table->dropForeign('fk_expenses_9325aebf7e');
            $table->dropForeign('fk_expenses_4ee7679ca5');
            $table->dropForeign('fk_expenses_6aed88530f');
            $table->dropForeign('fk_expenses_378e66e226');
            $table->dropForeign('fk_expenses_d046da8d50');
            $table->dropForeign('fk_expenses_d0f90c8322');
        });

        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropForeign('fk_carrier_settlement_lines_b3c7cddea3');
            $table->dropForeign('fk_carrier_settlement_lines_3637755e4b');
            $table->dropForeign('fk_carrier_settlement_lines_da719fe1c4');
            $table->dropForeign('fk_carrier_settlement_lines_15d08bb160');
            $table->dropForeign('fk_carrier_settlement_lines_989c7867e1');
            $table->dropForeign('fk_carrier_settlement_lines_9325aebf7e');
            $table->dropForeign('fk_carrier_settlement_lines_1e4156bc0b');
            $table->dropForeign('fk_carrier_settlement_lines_6aed88530f');
            $table->dropForeign('fk_carrier_settlement_lines_d046da8d50');
            $table->dropForeign('fk_carrier_settlement_lines_d0f90c8322');
            $table->dropForeign('fk_carrier_settlement_lines_3988575ec8');
            $table->dropForeign('fk_carrier_settlement_lines_c98047af1c');
            $table->dropForeign('fk_carrier_settlement_lines_805dec760f');
            $table->dropForeign('fk_carrier_settlement_lines_94cc5a061f');
            $table->dropForeign('fk_carrier_settlement_lines_228b02ea22');
            $table->dropForeign('fk_carrier_settlement_lines_4570412ab1');
            $table->dropForeign('fk_carrier_settlement_lines_925c950e83');
            $table->dropForeign('fk_carrier_settlement_lines_2223ed5210');
            $table->dropForeign('fk_carrier_settlement_lines_9860c0e09b');
            $table->dropForeign('fk_carrier_settlement_lines_857b657a79');
            $table->dropForeign('fk_carrier_settlement_lines_0c47191ede');
            $table->dropForeign('fk_carrier_settlement_lines_d8429d5699');
            $table->dropForeign('fk_carrier_settlement_lines_8233cbb96c');
            $table->dropForeign('fk_carrier_settlement_lines_ed98a017a8');
            $table->dropForeign('fk_carrier_settlement_lines_70de42edf6');
        });

        Schema::table('remittance_statements', function (Blueprint $table): void {
            $table->dropForeign('fk_remittance_statements_b3c7cddea3');
            $table->dropForeign('fk_remittance_statements_2582706774');
            $table->dropForeign('fk_remittance_statements_6a16de7f77');
            $table->dropForeign('fk_remittance_statements_6aed88530f');
            $table->dropForeign('fk_remittance_statements_d046da8d50');
        });

        Schema::table('collections', function (Blueprint $table): void {
            $table->dropForeign('fk_collections_9325aebf7e');
        });

        Schema::table('carrier_operation_attempts', function (Blueprint $table): void {
            $table->dropForeign('fk_carrier_operation_attempts_9a9856b253');
        });

        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->dropForeign('fk_carrier_operations_b3c7cddea3');
            $table->dropForeign('fk_carrier_operations_9325aebf7e');
            $table->dropForeign('fk_carrier_operations_ca13a6b2c9');
            $table->dropForeign('fk_carrier_operations_4ee7679ca5');
            $table->dropForeign('fk_carrier_operations_e22455d8ca');
            $table->dropForeign('fk_carrier_operations_41c5e1821d');
            $table->dropForeign('fk_carrier_operations_176faee833');
            $table->dropForeign('fk_carrier_operations_ff6828c755');
            $table->dropForeign('fk_carrier_operations_4066108b3c');
        });

        Schema::table('shipment_events', function (Blueprint $table): void {
            $table->dropForeign('fk_shipment_events_9325aebf7e');
            $table->dropForeign('fk_shipment_events_fe4b4e1602');
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropForeign('fk_shipments_ca13a6b2c9');
            $table->dropForeign('fk_shipments_f48a5da11a');
            $table->dropForeign('fk_shipments_b3c7cddea3');
            $table->dropForeign('fk_shipments_b3b4e83a73');
            $table->dropForeign('fk_shipments_bc15dd68ce');
            $table->dropForeign('fk_shipments_02c54136b3');
            $table->dropForeign('fk_shipments_25270a5a20');
            $table->dropForeign('fk_shipments_d69c160f70');
        });

        Schema::table('free_shipping_rules', function (Blueprint $table): void {
            $table->dropForeign('fk_free_shipping_rules_c3adad4f81');
        });

        Schema::table('shipping_rates', function (Blueprint $table): void {
            $table->dropForeign('fk_shipping_rates_b3c7cddea3');
            $table->dropForeign('fk_shipping_rates_fd68d11272');
            $table->dropForeign('fk_shipping_rates_6fb667974b');
        });

        Schema::table('shipping_providers', function (Blueprint $table): void {
            $table->dropForeign('fk_shipping_providers_f89d6b6960');
            $table->dropForeign('fk_shipping_providers_fd68d11272');
        });

        Schema::table('return_items', function (Blueprint $table): void {
            $table->dropForeign('fk_return_items_4ee7679ca5');
            $table->dropForeign('fk_return_items_f63d0d3350');
            $table->dropForeign('fk_return_items_f48a5da11a');
            $table->dropForeign('fk_return_items_14f215ed6d');
            $table->dropForeign('fk_return_items_e8b4e1ba43');
            $table->dropForeign('fk_return_items_aaf597818c');
            $table->dropForeign('fk_return_items_14ba853e98');
            $table->dropForeign('fk_return_items_36a9296d11');
        });

        Schema::table('order_returns', function (Blueprint $table): void {
            $table->dropForeign('fk_order_returns_9325aebf7e');
            $table->dropForeign('fk_order_returns_ca13a6b2c9');
            $table->dropForeign('fk_order_returns_f48a5da11a');
            $table->dropForeign('fk_order_returns_9cd65364a6');
            $table->dropForeign('fk_order_returns_b5f82f238f');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign('fk_stock_movements_14f215ed6d');
            $table->dropForeign('fk_stock_movements_f63d0d3350');
            $table->dropForeign('fk_stock_movements_859adac0ef');
            $table->dropForeign('fk_stock_movements_fe4b4e1602');
            $table->dropForeign('fk_stock_movements_d046da8d50');
            $table->dropForeign('fk_stock_movements_36a9296d11');
            $table->dropForeign('fk_stock_movements_a2c9ce4719');
            $table->dropForeign('fk_stock_movements_e6592dc642');
        });

        Schema::table('order_history', function (Blueprint $table): void {
            $table->dropForeign('fk_order_history_ca13a6b2c9');
            $table->dropForeign('fk_order_history_cb5d6a7448');
            $table->dropForeign('fk_order_history_4fbb76793e');
            $table->dropForeign('fk_order_history_fe4b4e1602');
            $table->dropForeign('fk_order_history_8cd5f69ee7');
            $table->dropForeign('fk_order_history_3790f81ee9');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeign('fk_order_items_e22455d8ca');
            $table->dropForeign('fk_order_items_14f215ed6d');
            $table->dropForeign('fk_order_items_c3adad4f81');
            $table->dropForeign('fk_order_items_baeda0db73');
            $table->dropForeign('fk_order_items_b31f7a60fc');
            $table->dropForeign('fk_order_items_29b92b5d93');
            $table->dropForeign('fk_order_items_59bc361caa');
        });

        Schema::table('order_revisions', function (Blueprint $table): void {
            $table->dropForeign('fk_order_revisions_ca13a6b2c9');
            $table->dropForeign('fk_order_revisions_378e66e226');
            $table->dropForeign('fk_order_revisions_edc8ae80ff');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign('fk_orders_f4e34ae2ec');
            $table->dropForeign('fk_orders_eb4226caa5');
            $table->dropForeign('fk_orders_2a9ca5a1fd');
            $table->dropForeign('fk_orders_e50ff0c50c');
            $table->dropForeign('fk_orders_c1f8c8f7a4');
            $table->dropForeign('fk_orders_b41c795bd0');
            $table->dropForeign('fk_orders_7401f11da6');
            $table->dropForeign('fk_orders_ee67fd3519');
            $table->dropForeign('fk_orders_a114941ab3');
            $table->dropForeign('fk_orders_0d5c9ac004');
            $table->dropForeign('fk_orders_7f60a79ecc');
            $table->dropForeign('fk_orders_79f7bc6c67');
            $table->dropForeign('fk_orders_430d4a293b');
            $table->dropForeign('fk_orders_00146a1f46');
            $table->dropForeign('fk_orders_c1dd89c96a');
            $table->dropForeign('fk_orders_122c5334b9');
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropForeign('fk_cart_items_eb4226caa5');
            $table->dropForeign('fk_cart_items_14f215ed6d');
            $table->dropForeign('fk_cart_items_c3adad4f81');
            $table->dropForeign('fk_cart_items_b31f7a60fc');
            $table->dropForeign('fk_cart_items_29b92b5d93');
            $table->dropForeign('fk_cart_items_59bc361caa');
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropForeign('fk_carts_f4e34ae2ec');
        });

        Schema::table('navigation_events', function (Blueprint $table): void {
            $table->dropForeign('fk_navigation_events_5c3a09bf22');
            $table->dropForeign('fk_navigation_events_c3adad4f81');
            $table->dropForeign('fk_navigation_events_14f215ed6d');
            $table->dropForeign('fk_navigation_events_b31f7a60fc');
            $table->dropForeign('fk_navigation_events_fe9e538371');
            $table->dropForeign('fk_navigation_events_eb4226caa5');
            $table->dropForeign('fk_navigation_events_59bc361caa');
            $table->dropForeign('fk_navigation_events_09613a1a6f');
            $table->dropForeign('fk_navigation_events_2e86a84456');
        });

        Schema::table('visit_sessions', function (Blueprint $table): void {
            $table->dropForeign('fk_visit_sessions_f4e34ae2ec');
        });

        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->dropForeign('fk_product_reviews_c3adad4f81');
            $table->dropForeign('fk_product_reviews_f4e34ae2ec');
            $table->dropForeign('fk_product_reviews_f63d0d3350');
            $table->dropForeign('fk_product_reviews_9ebbb59674');
            $table->dropForeign('fk_product_reviews_f503fc1f02');
        });

        Schema::table('product_promotions', function (Blueprint $table): void {
            $table->dropForeign('fk_product_promotions_c3adad4f81');
            $table->dropForeign('fk_product_promotions_14f215ed6d');
            $table->dropForeign('fk_product_promotions_b31f7a60fc');
            $table->dropForeign('fk_product_promotions_29b92b5d93');
            $table->dropForeign('fk_product_promotions_59bc361caa');
        });

        Schema::table('product_tags', function (Blueprint $table): void {
            $table->dropForeign('fk_product_tags_c3adad4f81');
            $table->dropForeign('fk_product_tags_6040e81886');
            $table->dropForeign('fk_product_tags_e950e25458');
        });

        Schema::table('variant_option_values', function (Blueprint $table): void {
            $table->dropForeign('fk_variant_option_values_c3adad4f81');
            $table->dropForeign('fk_variant_option_values_14f215ed6d');
            $table->dropForeign('fk_variant_option_values_5fb9f6d52f');
            $table->dropForeign('fk_variant_option_values_9714733197');
            $table->dropForeign('fk_variant_option_values_29b92b5d93');
            $table->dropForeign('fk_variant_option_values_537c9500da');
            $table->dropForeign('fk_variant_option_values_ef33e1fc9b');
        });

        Schema::table('product_options', function (Blueprint $table): void {
            $table->dropForeign('fk_product_options_c3adad4f81');
            $table->dropForeign('fk_product_options_7b54484fae');
            $table->dropForeign('fk_product_options_12a5993334');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropForeign('fk_product_variants_c3adad4f81');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign('fk_products_4f0d62547a');
            $table->dropForeign('fk_products_0846ff10b7');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropForeign('fk_categories_7b54484fae');
            $table->dropForeign('fk_categories_d3fc3e3e3a');
            $table->dropForeign('fk_categories_0f3b800172');
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->dropForeign('fk_media_6fb667974b');
        });

        Schema::table('content_pages', function (Blueprint $table): void {
            $table->dropForeign('fk_content_pages_c3adad4f81');
        });

        Schema::table('shop_addresses', function (Blueprint $table): void {
            $table->dropForeign('fk_shop_addresses_176fd1ac1e');
            $table->dropForeign('fk_shop_addresses_e08e4d9849');
            $table->dropForeign('fk_shop_addresses_88543b6ab4');
        });

        Schema::table('shop', function (Blueprint $table): void {
            $table->dropForeign('fk_shop_bd28b60ea5');
            $table->dropForeign('fk_shop_8e7646ae6d');
        });
    }
};
