<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_revisions', function (Blueprint $table): void {
            $table->unique(['id', 'order_id'], 'uq_order_revisions_cc60f48a93');
            $table->unique(['id', 'order_id', 'delivery_mode'], 'uq_order_revisions_3ab12e7b89');
            $table->unique(['id', 'order_id', 'pickup_point_uuid'], 'uq_order_revisions_0fbac59544');
            $table->unique(['order_id', 'revision_number'], 'uq_order_revisions_67e1c1b351');
            $table->index(['author_id'], 'ix_order_revisions_378e66e226');
            $table->index(['free_shipping_rule_id'], 'ix_order_revisions_edc8ae80ff');
        });

        Schema::table('billing_rules', function (Blueprint $table): void {
            $table->unique(['id', 'document_type', 'fiscal_year', 'record_type'], 'uq_billing_rules_461178d473');
            $table->unique(['id', 'record_type'], 'uq_billing_rules_8e50b89e8a');
            $table->unique(['document_type', 'fiscal_year', 'sequence_slot'], 'uq_billing_rules_c8aa6027ef');
            $table->unique(['code', 'version'], 'uq_billing_rules_f5b433d863');
            $table->index(['record_type', 'code', 'policy_status', 'effective_at'], 'ix_billing_rules_d6e1239708');
            $table->index(['validated_by_id'], 'ix_billing_rules_6a16de7f77');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->unique(['id', 'order_id'], 'uq_invoices_cc60f48a93');
            $table->unique(['id', 'order_id', 'revision_id', 'document_type'], 'uq_invoices_427443939d');
            $table->unique(['id', 'order_id', 'revision_id', 'document_type', 'original_invoice_id'], 'uq_invoices_968d27f2e4');
            $table->unique(['number'], 'uq_invoices_12886f9d00');
            $table->unique(['sequence_id', 'sequence_number'], 'uq_invoices_ba5a89d2cf');
            $table->unique(['operation_key'], 'uq_invoices_c8ff3469da');
            $table->index(['sequence_id', 'document_type', 'fiscal_year', 'sequence_record_type'], 'ix_invoices_b70ad3b9df');
            $table->index(['revision_id', 'order_id'], 'ix_invoices_ff6828c755');
            $table->index(['original_invoice_id', 'order_id'], 'ix_invoices_3a27fee5c5');
            $table->index(['incident_id', 'order_id'], 'ix_invoices_4e2d7fc88d');
            $table->index(['order_id'], 'ix_invoices_ca13a6b2c9');
            $table->index(['media_id'], 'ix_invoices_d3fc3e3e3a');
            $table->index(['issued_by_id'], 'ix_invoices_35693fc42b');
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->unique(['id', 'order_id', 'shipped_revision_id'], 'uq_shipments_ccccb20e50');
            $table->unique(['id', 'order_id'], 'uq_shipments_cc60f48a93');
            $table->unique(['id', 'provider_id'], 'uq_shipments_b8600d5a3c');
            $table->unique(['order_id'], 'uq_shipments_ca13a6b2c9');
            $table->unique(['provider_id', 'tracking'], 'uq_shipments_58866182c8');
            $table->unique(['provider_id', 'merchant_reference'], 'uq_shipments_e5751a4df4');
            $table->index(['shipped_revision_id', 'order_id', 'delivery_mode'], 'ix_shipments_25270a5a20');
            $table->index(['shipped_revision_id', 'order_id', 'pickup_point_uuid'], 'ix_shipments_d69c160f70');
            $table->index(['provider_id', 'status'], 'ix_shipments_166f81d0c0');
            $table->index(['label_media_id'], 'ix_shipments_b3b4e83a73');
            $table->index(['assigned_by_id'], 'ix_shipments_bc15dd68ce');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unique(['id', 'revision_id'], 'uq_order_items_dc5c41eb44');
            $table->unique(['id', 'variant_id'], 'uq_order_items_15acc595bd');
            $table->unique(['id', 'product_id'], 'uq_order_items_73097e04df');
            $table->index(['variant_id', 'reservation_status', 'revision_id', 'id'], 'ix_order_items_c1b518811c');
            $table->index(['revision_id', 'reservation_status', 'id'], 'ix_order_items_f75230a688');
            $table->index(['variant_id', 'product_id'], 'ix_order_items_29b92b5d93');
            $table->index(['sales_page_id', 'product_id'], 'ix_order_items_59bc361caa');
            $table->index(['product_id'], 'ix_order_items_c3adad4f81');
            $table->index(['promotion_id'], 'ix_order_items_baeda0db73');
        });

        Schema::table('order_returns', function (Blueprint $table): void {
            $table->unique(['id', 'shipment_id'], 'uq_order_returns_4baf6d51f5');
            $table->unique(['id', 'shipped_revision_id'], 'uq_order_returns_d3d9efb71e');
            $table->unique(['id', 'order_id'], 'uq_order_returns_cc60f48a93');
            $table->unique(['shipment_id'], 'uq_order_returns_9325aebf7e');
            $table->index(['shipment_id', 'order_id', 'shipped_revision_id'], 'ix_order_returns_b5f82f238f');
            $table->index(['order_id'], 'ix_order_returns_ca13a6b2c9');
            $table->index(['shipped_revision_id'], 'ix_order_returns_f48a5da11a');
            $table->index(['received_by_id'], 'ix_order_returns_9cd65364a6');
        });

        Schema::table('order_incidents', function (Blueprint $table): void {
            $table->unique(['id', 'order_id'], 'uq_order_incidents_cc60f48a93');
            $table->index(['shipment_id', 'order_id', 'shipped_revision_id'], 'ix_order_incidents_b5f82f238f');
            $table->index(['order_id', 'status'], 'ix_order_incidents_58978f474d');
            $table->index(['order_item_id', 'shipped_revision_id'], 'ix_order_incidents_14ba853e98');
            $table->index(['return_id', 'shipment_id'], 'ix_order_incidents_a663f454eb');
            $table->index(['shipped_revision_id'], 'ix_order_incidents_f48a5da11a');
            $table->index(['opened_by_id'], 'ix_order_incidents_db5c37fda7');
            $table->index(['validated_by_id'], 'ix_order_incidents_6a16de7f77');
        });

        Schema::table('return_items', function (Blueprint $table): void {
            $table->unique(['id', 'variant_id'], 'uq_return_items_15acc595bd');
            $table->unique(['return_id', 'order_item_id'], 'uq_return_items_feb81fbcb0');
            $table->index(['return_id', 'shipped_revision_id'], 'ix_return_items_aaf597818c');
            $table->index(['order_item_id', 'shipped_revision_id'], 'ix_return_items_14ba853e98');
            $table->index(['order_item_id', 'variant_id'], 'ix_return_items_36a9296d11');
            $table->index(['shipped_revision_id'], 'ix_return_items_f48a5da11a');
            $table->index(['variant_id'], 'ix_return_items_14f215ed6d');
            $table->index(['inspected_by_id'], 'ix_return_items_e8b4e1ba43');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unique(['id', 'product_id'], 'uq_product_variants_73097e04df');
            $table->unique(['sku'], 'uq_product_variants_bc7b047a69');
            $table->unique(['product_id', 'combination_signature'], 'uq_product_variants_1d03bffc80');
            $table->index(['product_id', 'is_active'], 'ix_product_variants_c3540f7294');
        });

        Schema::table('product_options', function (Blueprint $table): void {
            $table->unique(['id', 'product_id', 'record_type'], 'uq_product_options_71161dc6f3');
            $table->unique(['id', 'parent_id', 'product_id', 'record_type'], 'uq_product_options_28eb9ab60d');
            $table->unique(['parent_id', 'identity_code'], 'uq_product_options_66c74ef23d');
            $table->index(['product_id', 'record_type', 'deleted_at', 'position', 'id'], 'ix_product_options_93c4b10c5e');
            $table->index(['parent_id', 'product_id', 'parent_record_type'], 'ix_product_options_12a5993334');
        });

        Schema::table('content_pages', function (Blueprint $table): void {
            $table->unique(['id', 'product_id'], 'uq_content_pages_73097e04df');
            $table->unique(['id', 'page_kind'], 'uq_content_pages_b6521fb5df');
            $table->unique(['page_kind', 'slug'], 'uq_content_pages_8da27bc49c');
            $table->index(['page_kind', 'is_published', 'deleted_at', 'published_at', 'id'], 'ix_content_pages_a80ae424cf');
            $table->index(['product_id'], 'ix_content_pages_c3adad4f81');
        });

        Schema::table('shop_addresses', function (Blueprint $table): void {
            $table->unique(['id', 'shop_id', 'record_type'], 'uq_shop_addresses_35c9ecc8c2');
            $table->unique(['shop_id', 'primary_slot'], 'uq_shop_addresses_95137c0c10');
            $table->index(['shop_id', 'record_type', 'deleted_at', 'visible', 'position', 'id'], 'ix_shop_addresses_458d0048ae');
            $table->index(['shop_address_id', 'shop_id', 'shop_address_type'], 'ix_shop_addresses_88543b6ab4');
        });

        Schema::table('shipping_rates', function (Blueprint $table): void {
            $table->unique(['id', 'carrier_account_id', 'record_type'], 'uq_shipping_rates_a8ca41906b');
            $table->unique(['record_type', 'carrier_account_id', 'starts_at'], 'uq_shipping_rates_4542e653d8');
            $table->unique(['record_type', 'provider_scope_id', 'province_uuid', 'municipality_scope_uuid', 'delivery_mode', 'service_type', 'current_slot'], 'uq_shipping_rates_6c2746eef2');
            $table->index(['record_type', 'carrier_account_id', 'is_active', 'starts_at'], 'ix_shipping_rates_5343324d31');
            $table->index(['provider_id'], 'ix_shipping_rates_b3c7cddea3');
            $table->index(['carrier_account_id'], 'ix_shipping_rates_fd68d11272');
            $table->index(['created_by_id'], 'ix_shipping_rates_6fb667974b');
        });

        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->unique(['id', 'provider_id', 'record_type'], 'uq_carrier_settlement_lines_b1b8a4b1d2');
            $table->unique(['id', 'record_type', 'provider_id'], 'uq_carrier_settlement_lines_630034caf9');
            $table->unique(['id', 'record_type', 'provider_id', 'collection_id'], 'uq_carrier_settlement_lines_684ab1128a');
            $table->unique(['id', 'record_type', 'provider_id', 'carrier_fee_id'], 'uq_carrier_settlement_lines_3c35dc75d5');
            $table->unique(['id', 'record_type', 'provider_id', 'receivable_id'], 'uq_carrier_settlement_lines_51e0ae09e0');
            $table->unique(['id', 'record_type', 'provider_id', 'shipment_id'], 'uq_carrier_settlement_lines_09568d635d');
            $table->unique(['reversal_of_id'], 'uq_carrier_settlement_lines_d046da8d50');
            $table->index(['reversal_of_id', 'record_type', 'provider_id', 'collection_id'], 'ix_carrier_settlement_lines_925c950e83');
            $table->index(['reversal_of_id', 'record_type', 'provider_id', 'carrier_fee_id'], 'ix_carrier_settlement_lines_2223ed5210');
            $table->index(['reversal_of_id', 'record_type', 'provider_id', 'receivable_id'], 'ix_carrier_settlement_lines_9860c0e09b');
            $table->index(['reversal_of_id', 'record_type', 'provider_id', 'shipment_id'], 'ix_carrier_settlement_lines_857b657a79');
            $table->index(['correction_of_id', 'record_type', 'provider_id', 'collection_id'], 'ix_carrier_settlement_lines_d8429d5699');
            $table->index(['correction_of_id', 'record_type', 'provider_id', 'carrier_fee_id'], 'ix_carrier_settlement_lines_8233cbb96c');
            $table->index(['correction_of_id', 'record_type', 'provider_id', 'receivable_id'], 'ix_carrier_settlement_lines_ed98a017a8');
            $table->index(['correction_of_id', 'record_type', 'provider_id', 'shipment_id'], 'ix_carrier_settlement_lines_70de42edf6');
            $table->index(['record_type', 'receivable_id', 'performed_at'], 'ix_carrier_settlement_lines_83c607937c');
            $table->index(['carrier_fee_id', 'shipment_id', 'provider_id'], 'ix_carrier_settlement_lines_94cc5a061f');
            $table->index(['record_type', 'collection_id'], 'ix_carrier_settlement_lines_3079f88711');
            $table->index(['record_type', 'carrier_fee_id'], 'ix_carrier_settlement_lines_1770ae7898');
            $table->index(['record_type', 'remittance_statement_id'], 'ix_carrier_settlement_lines_f7f2c13bee');
            $table->index(['record_type', 'shipment_id'], 'ix_carrier_settlement_lines_96107c5138');
            $table->index(['remittance_statement_id', 'provider_id'], 'ix_carrier_settlement_lines_3988575ec8');
            $table->index(['shipment_id', 'provider_id'], 'ix_carrier_settlement_lines_c98047af1c');
            $table->index(['collection_id', 'shipment_id'], 'ix_carrier_settlement_lines_805dec760f');
            $table->index(['receivable_id', 'provider_id'], 'ix_carrier_settlement_lines_228b02ea22');
            $table->index(['provider_id'], 'ix_carrier_settlement_lines_b3c7cddea3');
            $table->index(['replacement_order_id'], 'ix_carrier_settlement_lines_1e4156bc0b');
            $table->index(['proof_media_id'], 'ix_carrier_settlement_lines_6aed88530f');
        });

        Schema::table('carrier_receivables', function (Blueprint $table): void {
            $table->unique(['id', 'provider_id'], 'uq_carrier_receivables_b8600d5a3c');
            $table->unique(['operation_key'], 'uq_carrier_receivables_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_carrier_receivables_d046da8d50');
            $table->index(['provider_id', 'status', 'remaining_amount'], 'ix_carrier_receivables_aeac33c348');
            $table->index(['original_fee_payment_id', 'provider_id', 'original_fee_payment_record_type'], 'ix_carrier_receivables_c187825194');
            $table->index(['reversal_of_id', 'provider_id'], 'ix_carrier_receivables_82e929ff12');
            $table->index(['carrier_fee_id'], 'ix_carrier_receivables_15d08bb160');
        });

        Schema::table('remittance_statements', function (Blueprint $table): void {
            $table->unique(['id', 'provider_id'], 'uq_remittance_statements_b8600d5a3c');
            $table->unique(['provider_id', 'number'], 'uq_remittance_statements_fcda4f1488');
            $table->unique(['operation_key'], 'uq_remittance_statements_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_remittance_statements_d046da8d50');
            $table->index(['carrier_remittance_batch_id', 'status'], 'ix_remittance_statements_19f845dd4a');
            $table->index(['validated_by_id'], 'ix_remittance_statements_6a16de7f77');
            $table->index(['proof_media_id'], 'ix_remittance_statements_6aed88530f');
        });

        Schema::table('collections', function (Blueprint $table): void {
            $table->unique(['id', 'shipment_id'], 'uq_collections_4baf6d51f5');
            $table->unique(['shipment_id'], 'uq_collections_9325aebf7e');
        });

        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->unique(['id', 'shipment_id', 'provider_id'], 'uq_carrier_fees_71fa8c67a5');
            $table->unique(['operation_key'], 'uq_carrier_fees_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_carrier_fees_d046da8d50');
            $table->index(['source_rate_id', 'carrier_account_id', 'source_rate_record_type'], 'ix_carrier_fees_3d038b8aa4');
            $table->index(['shipment_id', 'status'], 'ix_carrier_fees_002c46552c');
            $table->index(['shipment_id', 'provider_id'], 'ix_carrier_fees_c98047af1c');
            $table->index(['return_id', 'shipment_id'], 'ix_carrier_fees_a663f454eb');
            $table->index(['provider_id'], 'ix_carrier_fees_b3c7cddea3');
            $table->index(['carrier_account_id'], 'ix_carrier_fees_fd68d11272');
            $table->index(['proof_media_id'], 'ix_carrier_fees_6aed88530f');
            $table->index(['correction_of_id'], 'ix_carrier_fees_d0f90c8322');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->unique(['id', 'variant_id'], 'uq_stock_movements_15acc595bd');
            $table->unique(['variant_id', 'variant_sequence'], 'uq_stock_movements_2af188f970');
            $table->unique(['operation_key'], 'uq_stock_movements_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_stock_movements_d046da8d50');
            $table->index(['variant_id', 'created_at', 'id'], 'ix_stock_movements_efb6058ec1');
            $table->index(['return_item_id', 'created_at', 'id'], 'ix_stock_movements_8b6c8dd49b');
            $table->index(['order_item_id', 'variant_id'], 'ix_stock_movements_36a9296d11');
            $table->index(['return_item_id', 'variant_id'], 'ix_stock_movements_a2c9ce4719');
            $table->index(['reversal_of_id', 'variant_id'], 'ix_stock_movements_e6592dc642');
            $table->index(['actor_id'], 'ix_stock_movements_fe4b4e1602');
        });

        Schema::table('collection_entries', function (Blueprint $table): void {
            $table->unique(['id', 'collection_id'], 'uq_collection_entries_622b560def');
            $table->unique(['operation_key'], 'uq_collection_entries_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_collection_entries_d046da8d50');
            $table->index(['collection_id', 'collected_at'], 'ix_collection_entries_678b81dca4');
            $table->index(['reversal_of_id', 'collection_id'], 'ix_collection_entries_0bea1a3527');
            $table->index(['correction_of_id', 'collection_id'], 'ix_collection_entries_30763314bb');
            $table->index(['verified_by_id'], 'ix_collection_entries_b8ab8c1e46');
            $table->index(['proof_media_id'], 'ix_collection_entries_6aed88530f');
        });

        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->unique(['id', 'order_id', 'incident_id'], 'uq_customer_adjustments_10f264567a');
            $table->unique(['operation_key'], 'uq_customer_adjustments_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_customer_adjustments_d046da8d50');
            $table->index(['reversal_of_id', 'order_id', 'incident_id'], 'ix_customer_adjustments_1220d124fe');
            $table->index(['correction_of_id', 'order_id', 'incident_id'], 'ix_customer_adjustments_1ebd6bb067');
            $table->index(['return_id', 'status'], 'ix_customer_adjustments_e25618ae97');
            $table->index(['incident_id', 'status'], 'ix_customer_adjustments_dc6588423e');
            $table->index(['incident_id', 'order_id'], 'ix_customer_adjustments_4e2d7fc88d');
            $table->index(['order_id'], 'ix_customer_adjustments_ca13a6b2c9');
            $table->index(['credit_note_id'], 'ix_customer_adjustments_d61185a1fb');
            $table->index(['validated_by_id'], 'ix_customer_adjustments_6a16de7f77');
            $table->index(['proof_media_id'], 'ix_customer_adjustments_6aed88530f');
        });

        Schema::table('commercial_corrections', function (Blueprint $table): void {
            $table->unique(['id', 'order_id', 'source_revision_id'], 'uq_commercial_corrections_0885846f78');
            $table->unique(['correction_of_id'], 'uq_commercial_corrections_d0f90c8322');
            $table->unique(['id', 'source_revision_id'], 'uq_commercial_corrections_602a26090c');
            $table->index(['correction_of_id', 'order_id', 'source_revision_id'], 'ix_commercial_corrections_8834292179');
            $table->index(['status', 'effective_at'], 'ix_commercial_corrections_f4dd9c9e5d');
            $table->index(['source_revision_id', 'order_id'], 'ix_commercial_corrections_a5c7e0ed56');
            $table->index(['incident_id', 'order_id'], 'ix_commercial_corrections_4e2d7fc88d');
            $table->index(['order_id'], 'ix_commercial_corrections_ca13a6b2c9');
            $table->index(['actor_id'], 'ix_commercial_corrections_fe4b4e1602');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->unique(['id', 'record_type'], 'uq_categories_8e50b89e8a');
            $table->unique(['record_type', 'slug'], 'uq_categories_f285a07437');
            $table->index(['parent_id', 'parent_record_type'], 'ix_categories_0f3b800172');
            $table->index(['media_id'], 'ix_categories_d3fc3e3e3a');
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->unique(['name', 'guard_name'], 'uq_permissions_9c4ed99553');
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->unique(['name', 'guard_name'], 'uq_roles_9c4ed99553');
            $table->unique(['guard_name', 'permission_signature'], 'uq_roles_bf8a2fab06');
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->unique(['model_type', 'model_id', 'collection_name', 'primary_slot'], 'uq_media_ba36efe3ba');
            $table->index(['created_by_id'], 'ix_media_6fb667974b');
        });

        Schema::table('shop', function (Blueprint $table): void {
            $table->unique(['tenant_uuid'], 'uq_shop_40ae0da513');
            $table->index(['logo_media_id'], 'ix_shop_bd28b60ea5');
            $table->index(['favicon_media_id'], 'ix_shop_8e7646ae6d');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unique(['slug'], 'uq_products_cd03861f0f');
            $table->index(['category_id', 'category_record_type'], 'ix_products_0846ff10b7');
        });

        Schema::table('variant_option_values', function (Blueprint $table): void {
            $table->unique(['variant_id', 'option_id'], 'uq_variant_option_values_6b3172664d');
            $table->index(['value_id', 'option_id', 'product_id', 'value_record_type'], 'ix_variant_option_values_ef33e1fc9b');
            $table->index(['option_id', 'product_id', 'option_record_type'], 'ix_variant_option_values_537c9500da');
            $table->index(['variant_id', 'product_id'], 'ix_variant_option_values_29b92b5d93');
            $table->index(['product_id'], 'ix_variant_option_values_c3adad4f81');
        });

        Schema::table('product_tags', function (Blueprint $table): void {
            $table->unique(['product_id', 'tag_id'], 'uq_product_tags_64a1fb9477');
            $table->index(['tag_id', 'tag_record_type'], 'ix_product_tags_e950e25458');
        });

        Schema::table('visitors', function (Blueprint $table): void {
            $table->unique(['token_hash'], 'uq_visitors_e6511e19f6');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unique(['number'], 'uq_orders_12886f9d00');
            $table->unique(['submission_key'], 'uq_orders_4903422f6d');
            $table->unique(['cart_id'], 'uq_orders_eb4226caa5');
            $table->unique(['original_return_id', 'unpaid_resend_slot'], 'uq_orders_41c1334e87');
            $table->index(['commercial_status', 'created_at'], 'ix_orders_9625194f6a');
            $table->index(['original_incident_id', 'commercial_status'], 'ix_orders_6258548af6');
            $table->index(['current_revision_id', 'id'], 'ix_orders_79f7bc6c67');
            $table->index(['confirmed_revision_id', 'id'], 'ix_orders_430d4a293b');
            $table->index(['original_incident_id', 'original_order_id'], 'ix_orders_00146a1f46');
            $table->index(['original_return_id', 'original_order_id'], 'ix_orders_c1dd89c96a');
            $table->index(['original_sales_page_id', 'original_sales_page_kind'], 'ix_orders_122c5334b9');
            $table->index(['original_order_id'], 'ix_orders_b41c795bd0');
            $table->index(['visitor_id'], 'ix_orders_f4e34ae2ec');
            $table->index(['original_session_id'], 'ix_orders_2a9ca5a1fd');
            $table->index(['confirmation_owner_id'], 'ix_orders_0d5c9ac004');
            $table->index(['operationally_confirmed_by_id'], 'ix_orders_7f60a79ecc');
        });

        Schema::table('shipping_providers', function (Blueprint $table): void {
            $table->unique(['carrier_account_id'], 'uq_shipping_providers_fd68d11272');
            $table->index(['user_id'], 'ix_shipping_providers_f89d6b6960');
        });

        Schema::table('shipment_events', function (Blueprint $table): void {
            $table->unique(['deduplication_key'], 'uq_shipment_events_5da4dfb656');
            $table->index(['shipment_id', 'observed_at'], 'ix_shipment_events_43ceaaa97d');
            $table->index(['shipment_id', 'occurred_at'], 'ix_shipment_events_af41a9d5c4');
            $table->index(['payload_expires_at'], 'ix_shipment_events_7bc826d864');
            $table->index(['actor_id'], 'ix_shipment_events_fe4b4e1602');
        });

        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->unique(['operation_key'], 'uq_carrier_operations_c8ff3469da');
            $table->index(['status', 'next_attempt_at'], 'ix_carrier_operations_bef5422393');
            $table->index(['shipment_id', 'status'], 'ix_carrier_operations_002c46552c');
            $table->index(['revision_id', 'order_id'], 'ix_carrier_operations_ff6828c755');
            $table->index(['shipment_id', 'order_id'], 'ix_carrier_operations_4066108b3c');
            $table->index(['request_expires_at'], 'ix_carrier_operations_772a03f7ab');
            $table->index(['provider_id'], 'ix_carrier_operations_b3c7cddea3');
            $table->index(['order_id'], 'ix_carrier_operations_ca13a6b2c9');
            $table->index(['return_id'], 'ix_carrier_operations_4ee7679ca5');
            $table->index(['superseded_by_operation_id'], 'ix_carrier_operations_41c5e1821d');
            $table->index(['triggered_by_id'], 'ix_carrier_operations_176faee833');
        });

        Schema::table('carrier_operation_attempts', function (Blueprint $table): void {
            $table->unique(['operation_id', 'attempt_number'], 'uq_carrier_operation_attempts_5e2bb8e610');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->unique(['operation_key'], 'uq_expenses_c8ff3469da');
            $table->unique(['reversal_of_id'], 'uq_expenses_d046da8d50');
            $table->index(['expense_date', 'product_id'], 'ix_expenses_ee736baad5');
            $table->index(['shipment_id'], 'ix_expenses_9325aebf7e');
            $table->index(['return_id'], 'ix_expenses_4ee7679ca5');
            $table->index(['product_id'], 'ix_expenses_c3adad4f81');
            $table->index(['order_id'], 'ix_expenses_ca13a6b2c9');
            $table->index(['proof_media_id'], 'ix_expenses_6aed88530f');
            $table->index(['author_id'], 'ix_expenses_378e66e226');
            $table->index(['correction_of_id'], 'ix_expenses_d0f90c8322');
        });

        Schema::table('order_documents', function (Blueprint $table): void {
            $table->unique(['number', 'document_version'], 'uq_order_documents_866a3a6347');
            $table->index(['revision_id', 'order_id'], 'ix_order_documents_ff6828c755');
            $table->index(['order_id'], 'ix_order_documents_ca13a6b2c9');
            $table->index(['media_id'], 'ix_order_documents_d3fc3e3e3a');
            $table->index(['generated_by_id'], 'ix_order_documents_dceaab373c');
        });

        Schema::table('billing_obligations', function (Blueprint $table): void {
            $table->unique(['invoice_id'], 'uq_billing_obligations_16a54288bc');
            $table->index(['invoice_id', 'order_id', 'revision_id', 'document_type', 'original_invoice_id'], 'ix_billing_obligations_0291334460');
            $table->index(['status', 'next_attempt_at'], 'ix_billing_obligations_bef5422393');
            $table->index(['billing_rule_id', 'billing_rule_record_type'], 'ix_billing_obligations_1bae90140d');
            $table->index(['revision_id', 'order_id'], 'ix_billing_obligations_ff6828c755');
            $table->index(['order_id'], 'ix_billing_obligations_ca13a6b2c9');
            $table->index(['original_invoice_id'], 'ix_billing_obligations_ec080ce729');
        });

        Schema::table('commercial_correction_lines', function (Blueprint $table): void {
            $table->unique(['correction_id', 'order_item_id'], 'uq_commercial_correction_lines_7a03ec309d');
            $table->index(['correction_id', 'source_revision_id'], 'ix_commercial_correction_lines_a8c9aa0cb7');
            $table->index(['order_item_id', 'source_revision_id'], 'ix_commercial_correction_lines_37c2fc4d66');
            $table->index(['source_revision_id'], 'ix_commercial_correction_lines_d329d3e500');
        });

        Schema::table('carrier_accounts', function (Blueprint $table): void {
            $table->unique(['carrier_uuid', 'external_account_id'], 'uq_carrier_accounts_573826f6df');
            $table->index(['created_by_id'], 'ix_carrier_accounts_6fb667974b');
        });

        Schema::table('carrier_remittance_batches', function (Blueprint $table): void {
            $table->unique(['carrier_account_id', 'external_reference'], 'uq_carrier_remittance_batches_d428b45b18');
            $table->unique(['id', 'carrier_account_id'], 'uq_carrier_remittance_batches_d5e203b348');
            $table->unique(['reversal_of_id'], 'uq_carrier_remittance_batches_d046da8d50');
            $table->index(['carrier_account_id', 'status', 'received_at'], 'ix_carrier_remittance_batches_774f9fe1b3');
            $table->index(['reversal_of_id', 'carrier_account_id'], 'ix_carrier_remittance_batches_5f7d97d0bf');
            $table->index(['proof_media_id'], 'ix_carrier_remittance_batches_6aed88530f');
            $table->index(['validated_by_id'], 'ix_carrier_remittance_batches_6a16de7f77');
        });

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->index(['log_name', 'performed_at', 'id'], 'ix_activity_log_15c617967d');
            $table->index(['subject_type', 'subject_id'], 'ix_activity_log_bdcae89d30');
            $table->index(['causer_type', 'causer_id'], 'ix_activity_log_863811f917');
            $table->index(['correlation_id'], 'ix_activity_log_c787c4c20c');
        });

        Schema::table('order_history', function (Blueprint $table): void {
            $table->index(['order_id', 'created_at'], 'ix_order_history_67479325e1');
            $table->index(['previous_revision_id', 'order_id'], 'ix_order_history_8cd5f69ee7');
            $table->index(['next_revision_id', 'order_id'], 'ix_order_history_3790f81ee9');
            $table->index(['actor_id'], 'ix_order_history_fe4b4e1602');
        });

        Schema::table('visit_sessions', function (Blueprint $table): void {
            $table->index(['visitor_id', 'started_at'], 'ix_visit_sessions_772cffd42f');
        });

        Schema::table('navigation_events', function (Blueprint $table): void {
            $table->index(['session_id', 'occurred_at'], 'ix_navigation_events_0fdb027686');
            $table->index(['product_id', 'occurred_at'], 'ix_navigation_events_5e29e3a9d7');
            $table->index(['sales_page_id', 'occurred_at'], 'ix_navigation_events_e05dc7153a');
            $table->index(['sales_page_id', 'product_id'], 'ix_navigation_events_59bc361caa');
            $table->index(['sales_page_id', 'sales_page_kind'], 'ix_navigation_events_09613a1a6f');
            $table->index(['content_page_id', 'content_page_kind'], 'ix_navigation_events_2e86a84456');
            $table->index(['variant_id'], 'ix_navigation_events_14f215ed6d');
            $table->index(['cart_id'], 'ix_navigation_events_eb4226caa5');
        });

        Schema::table('model_has_roles', function (Blueprint $table): void {
            $table->index(['model_id', 'model_type'], 'ix_model_has_roles_941e11a770');
        });

        Schema::table('model_has_permissions', function (Blueprint $table): void {
            $table->index(['model_id', 'model_type'], 'ix_model_has_permissions_941e11a770');
        });

        Schema::table('sales_terms_acceptances', function (Blueprint $table): void {
            $table->index(['revision_id', 'order_id'], 'ix_sales_terms_acceptances_ff6828c755');
            $table->index(['order_id'], 'ix_sales_terms_acceptances_ca13a6b2c9');
        });

        Schema::table('product_promotions', function (Blueprint $table): void {
            $table->index(['variant_id', 'product_id'], 'ix_product_promotions_29b92b5d93');
            $table->index(['sales_page_id', 'product_id'], 'ix_product_promotions_59bc361caa');
            $table->index(['product_id'], 'ix_product_promotions_c3adad4f81');
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->index(['variant_id', 'product_id'], 'ix_cart_items_29b92b5d93');
            $table->index(['sales_page_id', 'product_id'], 'ix_cart_items_59bc361caa');
            $table->index(['cart_id'], 'ix_cart_items_eb4226caa5');
            $table->index(['product_id'], 'ix_cart_items_c3adad4f81');
        });

        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->index(['order_item_id', 'product_id'], 'ix_product_reviews_f503fc1f02');
            $table->index(['product_id'], 'ix_product_reviews_c3adad4f81');
            $table->index(['visitor_id'], 'ix_product_reviews_f4e34ae2ec');
            $table->index(['moderated_by_id'], 'ix_product_reviews_9ebbb59674');
        });

        Schema::table('order_incident_details', function (Blueprint $table): void {
            $table->index(['incident_id'], 'ix_order_incident_details_04dbd90085');
            $table->index(['author_id'], 'ix_order_incident_details_378e66e226');
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->index(['visitor_id'], 'ix_carts_f4e34ae2ec');
        });

        Schema::table('free_shipping_rules', function (Blueprint $table): void {
            $table->index(['product_id'], 'ix_free_shipping_rules_c3adad4f81');
        });

        Schema::table('role_has_permissions', function (Blueprint $table): void {
            $table->index(['role_id'], 'ix_role_has_permissions_26525afb8b');
        });

        Schema::table('team_invitations', function (Blueprint $table): void {
            $table->index(['initial_role_id'], 'ix_team_invitations_d5576bc50f');
            $table->index(['invited_by_id'], 'ix_team_invitations_e3979f2144');
        });
    }

    public function down(): void
    {
        Schema::table('carrier_remittance_batches', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_remittance_batches_6a16de7f77');
        });
        Schema::table('carrier_remittance_batches', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_remittance_batches_6aed88530f');
        });
        Schema::table('carrier_accounts', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_accounts_6fb667974b');
        });
        Schema::table('team_invitations', function (Blueprint $table): void {
            $table->dropIndex('ix_team_invitations_e3979f2144');
        });
        Schema::table('team_invitations', function (Blueprint $table): void {
            $table->dropIndex('ix_team_invitations_d5576bc50f');
        });
        Schema::table('role_has_permissions', function (Blueprint $table): void {
            $table->dropIndex('ix_role_has_permissions_26525afb8b');
        });
        Schema::table('commercial_correction_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_commercial_correction_lines_d329d3e500');
        });
        Schema::table('commercial_corrections', function (Blueprint $table): void {
            $table->dropIndex('ix_commercial_corrections_fe4b4e1602');
        });
        Schema::table('commercial_corrections', function (Blueprint $table): void {
            $table->dropIndex('ix_commercial_corrections_ca13a6b2c9');
        });
        Schema::table('billing_obligations', function (Blueprint $table): void {
            $table->dropIndex('ix_billing_obligations_ec080ce729');
        });
        Schema::table('billing_obligations', function (Blueprint $table): void {
            $table->dropIndex('ix_billing_obligations_ca13a6b2c9');
        });
        Schema::table('sales_terms_acceptances', function (Blueprint $table): void {
            $table->dropIndex('ix_sales_terms_acceptances_ca13a6b2c9');
        });
        Schema::table('billing_rules', function (Blueprint $table): void {
            $table->dropIndex('ix_billing_rules_6a16de7f77');
        });
        Schema::table('order_incident_details', function (Blueprint $table): void {
            $table->dropIndex('ix_order_incident_details_378e66e226');
        });
        Schema::table('order_incidents', function (Blueprint $table): void {
            $table->dropIndex('ix_order_incidents_6a16de7f77');
        });
        Schema::table('order_incidents', function (Blueprint $table): void {
            $table->dropIndex('ix_order_incidents_db5c37fda7');
        });
        Schema::table('order_incidents', function (Blueprint $table): void {
            $table->dropIndex('ix_order_incidents_f48a5da11a');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_invoices_35693fc42b');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_invoices_d3fc3e3e3a');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_invoices_ca13a6b2c9');
        });
        Schema::table('collection_entries', function (Blueprint $table): void {
            $table->dropIndex('ix_collection_entries_6aed88530f');
        });
        Schema::table('collection_entries', function (Blueprint $table): void {
            $table->dropIndex('ix_collection_entries_b8ab8c1e46');
        });
        Schema::table('carrier_receivables', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_receivables_15d08bb160');
        });
        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_fees_d0f90c8322');
        });
        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_fees_6aed88530f');
        });
        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_fees_fd68d11272');
        });
        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_fees_b3c7cddea3');
        });
        Schema::table('order_documents', function (Blueprint $table): void {
            $table->dropIndex('ix_order_documents_dceaab373c');
        });
        Schema::table('order_documents', function (Blueprint $table): void {
            $table->dropIndex('ix_order_documents_d3fc3e3e3a');
        });
        Schema::table('order_documents', function (Blueprint $table): void {
            $table->dropIndex('ix_order_documents_ca13a6b2c9');
        });
        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropIndex('ix_customer_adjustments_6aed88530f');
        });
        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropIndex('ix_customer_adjustments_6a16de7f77');
        });
        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropIndex('ix_customer_adjustments_d61185a1fb');
        });
        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropIndex('ix_customer_adjustments_ca13a6b2c9');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('ix_expenses_d0f90c8322');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('ix_expenses_378e66e226');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('ix_expenses_6aed88530f');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('ix_expenses_ca13a6b2c9');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('ix_expenses_c3adad4f81');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_6aed88530f');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_1e4156bc0b');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_b3c7cddea3');
        });
        Schema::table('remittance_statements', function (Blueprint $table): void {
            $table->dropIndex('ix_remittance_statements_6aed88530f');
        });
        Schema::table('remittance_statements', function (Blueprint $table): void {
            $table->dropIndex('ix_remittance_statements_6a16de7f77');
        });
        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_operations_176faee833');
        });
        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_operations_41c5e1821d');
        });
        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_operations_4ee7679ca5');
        });
        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_operations_ca13a6b2c9');
        });
        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_operations_b3c7cddea3');
        });
        Schema::table('shipment_events', function (Blueprint $table): void {
            $table->dropIndex('ix_shipment_events_fe4b4e1602');
        });
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropIndex('ix_shipments_bc15dd68ce');
        });
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropIndex('ix_shipments_b3b4e83a73');
        });
        Schema::table('free_shipping_rules', function (Blueprint $table): void {
            $table->dropIndex('ix_free_shipping_rules_c3adad4f81');
        });
        Schema::table('shipping_rates', function (Blueprint $table): void {
            $table->dropIndex('ix_shipping_rates_6fb667974b');
        });
        Schema::table('shipping_rates', function (Blueprint $table): void {
            $table->dropIndex('ix_shipping_rates_fd68d11272');
        });
        Schema::table('shipping_rates', function (Blueprint $table): void {
            $table->dropIndex('ix_shipping_rates_b3c7cddea3');
        });
        Schema::table('shipping_providers', function (Blueprint $table): void {
            $table->dropIndex('ix_shipping_providers_f89d6b6960');
        });
        Schema::table('return_items', function (Blueprint $table): void {
            $table->dropIndex('ix_return_items_e8b4e1ba43');
        });
        Schema::table('return_items', function (Blueprint $table): void {
            $table->dropIndex('ix_return_items_14f215ed6d');
        });
        Schema::table('return_items', function (Blueprint $table): void {
            $table->dropIndex('ix_return_items_f48a5da11a');
        });
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->dropIndex('ix_order_returns_9cd65364a6');
        });
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->dropIndex('ix_order_returns_f48a5da11a');
        });
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->dropIndex('ix_order_returns_ca13a6b2c9');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('ix_stock_movements_fe4b4e1602');
        });
        Schema::table('order_history', function (Blueprint $table): void {
            $table->dropIndex('ix_order_history_fe4b4e1602');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('ix_order_items_baeda0db73');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('ix_order_items_c3adad4f81');
        });
        Schema::table('order_revisions', function (Blueprint $table): void {
            $table->dropIndex('ix_order_revisions_edc8ae80ff');
        });
        Schema::table('order_revisions', function (Blueprint $table): void {
            $table->dropIndex('ix_order_revisions_378e66e226');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('ix_orders_7f60a79ecc');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('ix_orders_0d5c9ac004');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('ix_orders_2a9ca5a1fd');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('ix_orders_f4e34ae2ec');
        });
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropIndex('ix_cart_items_c3adad4f81');
        });
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropIndex('ix_cart_items_eb4226caa5');
        });
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropIndex('ix_carts_f4e34ae2ec');
        });
        Schema::table('navigation_events', function (Blueprint $table): void {
            $table->dropIndex('ix_navigation_events_eb4226caa5');
        });
        Schema::table('navigation_events', function (Blueprint $table): void {
            $table->dropIndex('ix_navigation_events_14f215ed6d');
        });
        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->dropIndex('ix_product_reviews_9ebbb59674');
        });
        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->dropIndex('ix_product_reviews_f4e34ae2ec');
        });
        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->dropIndex('ix_product_reviews_c3adad4f81');
        });
        Schema::table('product_promotions', function (Blueprint $table): void {
            $table->dropIndex('ix_product_promotions_c3adad4f81');
        });
        Schema::table('variant_option_values', function (Blueprint $table): void {
            $table->dropIndex('ix_variant_option_values_c3adad4f81');
        });
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('ix_categories_d3fc3e3e3a');
        });
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex('ix_media_6fb667974b');
        });
        Schema::table('content_pages', function (Blueprint $table): void {
            $table->dropIndex('ix_content_pages_c3adad4f81');
        });
        Schema::table('shop', function (Blueprint $table): void {
            $table->dropIndex('ix_shop_8e7646ae6d');
        });
        Schema::table('shop', function (Blueprint $table): void {
            $table->dropIndex('ix_shop_bd28b60ea5');
        });
        Schema::table('shipment_events', function (Blueprint $table): void {
            $table->dropIndex('ix_shipment_events_7bc826d864');
        });
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex('ix_activity_log_c787c4c20c');
        });
        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_operations_772a03f7ab');
        });
        Schema::table('order_incident_details', function (Blueprint $table): void {
            $table->dropIndex('ix_order_incident_details_04dbd90085');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('ix_expenses_4ee7679ca5');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('ix_expenses_9325aebf7e');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('ix_orders_b41c795bd0');
        });
        Schema::table('commercial_correction_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_commercial_correction_lines_37c2fc4d66');
        });
        Schema::table('commercial_correction_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_commercial_correction_lines_a8c9aa0cb7');
        });
        Schema::table('commercial_corrections', function (Blueprint $table): void {
            $table->dropIndex('ix_commercial_corrections_4e2d7fc88d');
        });
        Schema::table('commercial_corrections', function (Blueprint $table): void {
            $table->dropIndex('ix_commercial_corrections_a5c7e0ed56');
        });
        Schema::table('carrier_remittance_batches', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_remittance_batches_5f7d97d0bf');
        });
        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_fees_a663f454eb');
        });
        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_fees_c98047af1c');
        });
        Schema::table('product_tags', function (Blueprint $table): void {
            $table->dropIndex('ix_product_tags_e950e25458');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('ix_products_0846ff10b7');
        });
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('ix_categories_0f3b800172');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_invoices_4e2d7fc88d');
        });
        Schema::table('billing_obligations', function (Blueprint $table): void {
            $table->dropIndex('ix_billing_obligations_ff6828c755');
        });
        Schema::table('collection_entries', function (Blueprint $table): void {
            $table->dropIndex('ix_collection_entries_30763314bb');
        });
        Schema::table('collection_entries', function (Blueprint $table): void {
            $table->dropIndex('ix_collection_entries_0bea1a3527');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('ix_stock_movements_e6592dc642');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_228b02ea22');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_805dec760f');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_c98047af1c');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_3988575ec8');
        });
        Schema::table('carrier_receivables', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_receivables_82e929ff12');
        });
        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->dropIndex('ix_product_reviews_f503fc1f02');
        });
        Schema::table('navigation_events', function (Blueprint $table): void {
            $table->dropIndex('ix_navigation_events_2e86a84456');
        });
        Schema::table('navigation_events', function (Blueprint $table): void {
            $table->dropIndex('ix_navigation_events_09613a1a6f');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('ix_orders_122c5334b9');
        });
        Schema::table('navigation_events', function (Blueprint $table): void {
            $table->dropIndex('ix_navigation_events_59bc361caa');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('ix_order_items_59bc361caa');
        });
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropIndex('ix_cart_items_59bc361caa');
        });
        Schema::table('product_promotions', function (Blueprint $table): void {
            $table->dropIndex('ix_product_promotions_59bc361caa');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('ix_order_items_29b92b5d93');
        });
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropIndex('ix_cart_items_29b92b5d93');
        });
        Schema::table('product_promotions', function (Blueprint $table): void {
            $table->dropIndex('ix_product_promotions_29b92b5d93');
        });
        Schema::table('variant_option_values', function (Blueprint $table): void {
            $table->dropIndex('ix_variant_option_values_29b92b5d93');
        });
        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_operations_4066108b3c');
        });
        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_operations_ff6828c755');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('ix_stock_movements_a2c9ce4719');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('ix_stock_movements_36a9296d11');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('ix_orders_c1dd89c96a');
        });
        Schema::table('return_items', function (Blueprint $table): void {
            $table->dropIndex('ix_return_items_36a9296d11');
        });
        Schema::table('return_items', function (Blueprint $table): void {
            $table->dropIndex('ix_return_items_14ba853e98');
        });
        Schema::table('return_items', function (Blueprint $table): void {
            $table->dropIndex('ix_return_items_aaf597818c');
        });
        Schema::table('order_history', function (Blueprint $table): void {
            $table->dropIndex('ix_order_history_3790f81ee9');
        });
        Schema::table('order_history', function (Blueprint $table): void {
            $table->dropIndex('ix_order_history_8cd5f69ee7');
        });
        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropIndex('ix_customer_adjustments_4e2d7fc88d');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('ix_orders_00146a1f46');
        });
        Schema::table('order_incidents', function (Blueprint $table): void {
            $table->dropIndex('ix_order_incidents_a663f454eb');
        });
        Schema::table('order_incidents', function (Blueprint $table): void {
            $table->dropIndex('ix_order_incidents_14ba853e98');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_invoices_3a27fee5c5');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_invoices_ff6828c755');
        });
        Schema::table('order_documents', function (Blueprint $table): void {
            $table->dropIndex('ix_order_documents_ff6828c755');
        });
        Schema::table('sales_terms_acceptances', function (Blueprint $table): void {
            $table->dropIndex('ix_sales_terms_acceptances_ff6828c755');
        });
        Schema::table('billing_obligations', function (Blueprint $table): void {
            $table->dropIndex('ix_billing_obligations_1bae90140d');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('ix_orders_430d4a293b');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('ix_orders_79f7bc6c67');
        });
        Schema::table('navigation_events', function (Blueprint $table): void {
            $table->dropIndex('ix_navigation_events_e05dc7153a');
        });
        Schema::table('navigation_events', function (Blueprint $table): void {
            $table->dropIndex('ix_navigation_events_5e29e3a9d7');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_96107c5138');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_f7f2c13bee');
        });
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex('ix_activity_log_863811f917');
        });
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex('ix_activity_log_bdcae89d30');
        });
        Schema::table('model_has_permissions', function (Blueprint $table): void {
            $table->dropIndex('ix_model_has_permissions_941e11a770');
        });
        Schema::table('model_has_roles', function (Blueprint $table): void {
            $table->dropIndex('ix_model_has_roles_941e11a770');
        });
        Schema::table('commercial_corrections', function (Blueprint $table): void {
            $table->dropIndex('ix_commercial_corrections_f4dd9c9e5d');
        });
        Schema::table('billing_obligations', function (Blueprint $table): void {
            $table->dropIndex('ix_billing_obligations_bef5422393');
        });
        Schema::table('shipment_events', function (Blueprint $table): void {
            $table->dropIndex('ix_shipment_events_af41a9d5c4');
        });
        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropIndex('ix_customer_adjustments_dc6588423e');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('ix_orders_6258548af6');
        });
        Schema::table('order_incidents', function (Blueprint $table): void {
            $table->dropIndex('ix_order_incidents_58978f474d');
        });
        Schema::table('remittance_statements', function (Blueprint $table): void {
            $table->dropIndex('ix_remittance_statements_19f845dd4a');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('ix_expenses_ee736baad5');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_1770ae7898');
        });
        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_fees_002c46552c');
        });
        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropIndex('ix_customer_adjustments_e25618ae97');
        });
        Schema::table('collection_entries', function (Blueprint $table): void {
            $table->dropIndex('ix_collection_entries_678b81dca4');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_3079f88711');
        });
        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_operations_002c46552c');
        });
        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_operations_bef5422393');
        });
        Schema::table('shipment_events', function (Blueprint $table): void {
            $table->dropIndex('ix_shipment_events_43ceaaa97d');
        });
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropIndex('ix_shipments_166f81d0c0');
        });
        Schema::table('navigation_events', function (Blueprint $table): void {
            $table->dropIndex('ix_navigation_events_0fdb027686');
        });
        Schema::table('visit_sessions', function (Blueprint $table): void {
            $table->dropIndex('ix_visit_sessions_772cffd42f');
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropIndex('ix_product_variants_c3540f7294');
        });
        Schema::table('order_history', function (Blueprint $table): void {
            $table->dropIndex('ix_order_history_67479325e1');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('ix_orders_9625194f6a');
        });
        Schema::table('commercial_corrections', function (Blueprint $table): void {
            $table->dropIndex('ix_commercial_corrections_8834292179');
        });
        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropIndex('ix_customer_adjustments_1ebd6bb067');
        });
        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropIndex('ix_customer_adjustments_1220d124fe');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_94cc5a061f');
        });
        Schema::table('carrier_receivables', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_receivables_c187825194');
        });
        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_fees_3d038b8aa4');
        });
        Schema::table('variant_option_values', function (Blueprint $table): void {
            $table->dropIndex('ix_variant_option_values_537c9500da');
        });
        Schema::table('product_options', function (Blueprint $table): void {
            $table->dropIndex('ix_product_options_12a5993334');
        });
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->dropIndex('ix_order_returns_b5f82f238f');
        });
        Schema::table('order_incidents', function (Blueprint $table): void {
            $table->dropIndex('ix_order_incidents_b5f82f238f');
        });
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropIndex('ix_shipments_d69c160f70');
        });
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropIndex('ix_shipments_25270a5a20');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('ix_order_items_f75230a688');
        });
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex('ix_activity_log_15c617967d');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_83c607937c');
        });
        Schema::table('carrier_receivables', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_receivables_aeac33c348');
        });
        Schema::table('carrier_remittance_batches', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_remittance_batches_774f9fe1b3');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('ix_stock_movements_8b6c8dd49b');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('ix_stock_movements_efb6058ec1');
        });
        Schema::table('shop_addresses', function (Blueprint $table): void {
            $table->dropIndex('ix_shop_addresses_88543b6ab4');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_70de42edf6');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_ed98a017a8');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_8233cbb96c');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_d8429d5699');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_857b657a79');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_9860c0e09b');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_2223ed5210');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_settlement_lines_925c950e83');
        });
        Schema::table('variant_option_values', function (Blueprint $table): void {
            $table->dropIndex('ix_variant_option_values_ef33e1fc9b');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_invoices_b70ad3b9df');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('ix_order_items_c1b518811c');
        });
        Schema::table('shipping_rates', function (Blueprint $table): void {
            $table->dropIndex('ix_shipping_rates_5343324d31');
        });
        Schema::table('billing_rules', function (Blueprint $table): void {
            $table->dropIndex('ix_billing_rules_d6e1239708');
        });
        Schema::table('billing_obligations', function (Blueprint $table): void {
            $table->dropIndex('ix_billing_obligations_0291334460');
        });
        Schema::table('product_options', function (Blueprint $table): void {
            $table->dropIndex('ix_product_options_93c4b10c5e');
        });
        Schema::table('content_pages', function (Blueprint $table): void {
            $table->dropIndex('ix_content_pages_a80ae424cf');
        });
        Schema::table('shop_addresses', function (Blueprint $table): void {
            $table->dropIndex('ix_shop_addresses_458d0048ae');
        });
        Schema::table('commercial_corrections', function (Blueprint $table): void {
            $table->dropUnique('uq_commercial_corrections_602a26090c');
        });
        Schema::table('carrier_remittance_batches', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_remittance_batches_d046da8d50');
        });
        Schema::table('carrier_remittance_batches', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_remittance_batches_d5e203b348');
        });
        Schema::table('carrier_remittance_batches', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_remittance_batches_d428b45b18');
        });
        Schema::table('carrier_accounts', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_accounts_573826f6df');
        });
        Schema::table('commercial_correction_lines', function (Blueprint $table): void {
            $table->dropUnique('uq_commercial_correction_lines_7a03ec309d');
        });
        Schema::table('commercial_corrections', function (Blueprint $table): void {
            $table->dropUnique('uq_commercial_corrections_d0f90c8322');
        });
        Schema::table('billing_obligations', function (Blueprint $table): void {
            $table->dropUnique('uq_billing_obligations_16a54288bc');
        });
        Schema::table('billing_rules', function (Blueprint $table): void {
            $table->dropUnique('uq_billing_rules_f5b433d863');
        });
        Schema::table('billing_rules', function (Blueprint $table): void {
            $table->dropUnique('uq_billing_rules_c8aa6027ef');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('uq_invoices_c8ff3469da');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('uq_invoices_ba5a89d2cf');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('uq_invoices_12886f9d00');
        });
        Schema::table('collection_entries', function (Blueprint $table): void {
            $table->dropUnique('uq_collection_entries_d046da8d50');
        });
        Schema::table('collection_entries', function (Blueprint $table): void {
            $table->dropUnique('uq_collection_entries_c8ff3469da');
        });
        Schema::table('carrier_receivables', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_receivables_d046da8d50');
        });
        Schema::table('carrier_receivables', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_receivables_c8ff3469da');
        });
        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_fees_d046da8d50');
        });
        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_fees_c8ff3469da');
        });
        Schema::table('order_documents', function (Blueprint $table): void {
            $table->dropUnique('uq_order_documents_866a3a6347');
        });
        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropUnique('uq_customer_adjustments_d046da8d50');
        });
        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropUnique('uq_customer_adjustments_c8ff3469da');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropUnique('uq_expenses_d046da8d50');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropUnique('uq_expenses_c8ff3469da');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_settlement_lines_d046da8d50');
        });
        Schema::table('remittance_statements', function (Blueprint $table): void {
            $table->dropUnique('uq_remittance_statements_d046da8d50');
        });
        Schema::table('remittance_statements', function (Blueprint $table): void {
            $table->dropUnique('uq_remittance_statements_c8ff3469da');
        });
        Schema::table('remittance_statements', function (Blueprint $table): void {
            $table->dropUnique('uq_remittance_statements_fcda4f1488');
        });
        Schema::table('collections', function (Blueprint $table): void {
            $table->dropUnique('uq_collections_9325aebf7e');
        });
        Schema::table('carrier_operation_attempts', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_operation_attempts_5e2bb8e610');
        });
        Schema::table('carrier_operations', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_operations_c8ff3469da');
        });
        Schema::table('shipment_events', function (Blueprint $table): void {
            $table->dropUnique('uq_shipment_events_5da4dfb656');
        });
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropUnique('uq_shipments_e5751a4df4');
        });
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropUnique('uq_shipments_58866182c8');
        });
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropUnique('uq_shipments_ca13a6b2c9');
        });
        Schema::table('shipping_rates', function (Blueprint $table): void {
            $table->dropUnique('uq_shipping_rates_6c2746eef2');
        });
        Schema::table('shipping_rates', function (Blueprint $table): void {
            $table->dropUnique('uq_shipping_rates_4542e653d8');
        });
        Schema::table('shipping_providers', function (Blueprint $table): void {
            $table->dropUnique('uq_shipping_providers_fd68d11272');
        });
        Schema::table('return_items', function (Blueprint $table): void {
            $table->dropUnique('uq_return_items_feb81fbcb0');
        });
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->dropUnique('uq_order_returns_9325aebf7e');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropUnique('uq_stock_movements_d046da8d50');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropUnique('uq_stock_movements_c8ff3469da');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropUnique('uq_stock_movements_2af188f970');
        });
        Schema::table('order_revisions', function (Blueprint $table): void {
            $table->dropUnique('uq_order_revisions_67e1c1b351');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('uq_orders_41c1334e87');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('uq_orders_eb4226caa5');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('uq_orders_4903422f6d');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('uq_orders_12886f9d00');
        });
        Schema::table('visitors', function (Blueprint $table): void {
            $table->dropUnique('uq_visitors_e6511e19f6');
        });
        Schema::table('product_tags', function (Blueprint $table): void {
            $table->dropUnique('uq_product_tags_64a1fb9477');
        });
        Schema::table('variant_option_values', function (Blueprint $table): void {
            $table->dropUnique('uq_variant_option_values_6b3172664d');
        });
        Schema::table('product_options', function (Blueprint $table): void {
            $table->dropUnique('uq_product_options_66c74ef23d');
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique('uq_product_variants_1d03bffc80');
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique('uq_product_variants_bc7b047a69');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('uq_products_cd03861f0f');
        });
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique('uq_categories_f285a07437');
        });
        Schema::table('content_pages', function (Blueprint $table): void {
            $table->dropUnique('uq_content_pages_8da27bc49c');
        });
        Schema::table('shop_addresses', function (Blueprint $table): void {
            $table->dropUnique('uq_shop_addresses_95137c0c10');
        });
        Schema::table('shop', function (Blueprint $table): void {
            $table->dropUnique('uq_shop_40ae0da513');
        });
        Schema::table('media', function (Blueprint $table): void {
            $table->dropUnique('uq_media_ba36efe3ba');
        });
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique('uq_roles_bf8a2fab06');
        });
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique('uq_roles_9c4ed99553');
        });
        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropUnique('uq_permissions_9c4ed99553');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_settlement_lines_09568d635d');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_settlement_lines_51e0ae09e0');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_settlement_lines_3c35dc75d5');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_settlement_lines_684ab1128a');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_settlement_lines_630034caf9');
        });
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique('uq_categories_8e50b89e8a');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('uq_invoices_968d27f2e4');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('uq_invoices_427443939d');
        });
        Schema::table('commercial_corrections', function (Blueprint $table): void {
            $table->dropUnique('uq_commercial_corrections_0885846f78');
        });
        Schema::table('customer_adjustments', function (Blueprint $table): void {
            $table->dropUnique('uq_customer_adjustments_10f264567a');
        });
        Schema::table('collection_entries', function (Blueprint $table): void {
            $table->dropUnique('uq_collection_entries_622b560def');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropUnique('uq_stock_movements_15acc595bd');
        });
        Schema::table('carrier_fees', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_fees_71fa8c67a5');
        });
        Schema::table('collections', function (Blueprint $table): void {
            $table->dropUnique('uq_collections_4baf6d51f5');
        });
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropUnique('uq_shipments_b8600d5a3c');
        });
        Schema::table('remittance_statements', function (Blueprint $table): void {
            $table->dropUnique('uq_remittance_statements_b8600d5a3c');
        });
        Schema::table('carrier_receivables', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_receivables_b8600d5a3c');
        });
        Schema::table('carrier_settlement_lines', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_settlement_lines_b1b8a4b1d2');
        });
        Schema::table('shipping_rates', function (Blueprint $table): void {
            $table->dropUnique('uq_shipping_rates_a8ca41906b');
        });
        Schema::table('shop_addresses', function (Blueprint $table): void {
            $table->dropUnique('uq_shop_addresses_35c9ecc8c2');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropUnique('uq_order_items_73097e04df');
        });
        Schema::table('content_pages', function (Blueprint $table): void {
            $table->dropUnique('uq_content_pages_b6521fb5df');
        });
        Schema::table('content_pages', function (Blueprint $table): void {
            $table->dropUnique('uq_content_pages_73097e04df');
        });
        Schema::table('product_options', function (Blueprint $table): void {
            $table->dropUnique('uq_product_options_28eb9ab60d');
        });
        Schema::table('product_options', function (Blueprint $table): void {
            $table->dropUnique('uq_product_options_71161dc6f3');
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique('uq_product_variants_73097e04df');
        });
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropUnique('uq_shipments_cc60f48a93');
        });
        Schema::table('return_items', function (Blueprint $table): void {
            $table->dropUnique('uq_return_items_15acc595bd');
        });
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->dropUnique('uq_order_returns_cc60f48a93');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropUnique('uq_order_items_15acc595bd');
        });
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->dropUnique('uq_order_returns_d3d9efb71e');
        });
        Schema::table('order_incidents', function (Blueprint $table): void {
            $table->dropUnique('uq_order_incidents_cc60f48a93');
        });
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->dropUnique('uq_order_returns_4baf6d51f5');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropUnique('uq_order_items_dc5c41eb44');
        });
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropUnique('uq_shipments_ccccb20e50');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('uq_invoices_cc60f48a93');
        });
        Schema::table('order_revisions', function (Blueprint $table): void {
            $table->dropUnique('uq_order_revisions_0fbac59544');
        });
        Schema::table('order_revisions', function (Blueprint $table): void {
            $table->dropUnique('uq_order_revisions_3ab12e7b89');
        });
        Schema::table('billing_rules', function (Blueprint $table): void {
            $table->dropUnique('uq_billing_rules_8e50b89e8a');
        });
        Schema::table('billing_rules', function (Blueprint $table): void {
            $table->dropUnique('uq_billing_rules_461178d473');
        });
        Schema::table('order_revisions', function (Blueprint $table): void {
            $table->dropUnique('uq_order_revisions_cc60f48a93');
        });
    }
};
