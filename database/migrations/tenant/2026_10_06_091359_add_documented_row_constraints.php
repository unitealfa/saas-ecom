<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'shop' => ['id', 'uuid', 'tenant_uuid', 'logo_media_id', 'favicon_media_id', 'singleton', 'central_profile_version', 'shop_name', 'description', 'about', 'business_type', 'contact_email', 'contact_phone', 'contact_whatsapp', 'locale', 'currency', 'timezone', 'theme_code', 'colors', 'shipping_tax_configuration', 'cart_lifetime_days', 'created_at', 'updated_at'],
            'shop_addresses' => ['id', 'uuid', 'shop_id', 'shop_address_id', 'province_uuid', 'municipality_uuid', 'record_type', 'shop_address_type', 'label', 'position', 'is_primary', 'visible', 'payload', 'primary_slot', 'created_at', 'updated_at', 'deleted_at'],
            'content_pages' => ['id', 'uuid', 'product_id', 'page_kind', 'slug', 'type', 'title', 'content', 'meta_title', 'meta_description', 'canonical_url', 'indexable', 'is_published', 'published_at', 'version', 'created_at', 'updated_at', 'deleted_at'],
            'media' => ['id', 'uuid', 'created_by_id', 'storage_key', 'model_id', 'model_type', 'collection_name', 'disk', 'mime_type', 'original_name', 'size_bytes', 'width', 'height', 'duration_seconds', 'alt_text', 'visibility', 'position', 'is_primary', 'primary_slot', 'file_hash', 'created_at', 'updated_at', 'deleted_at'],
            'categories' => ['id', 'uuid', 'parent_id', 'media_id', 'record_type', 'parent_record_type', 'name', 'slug', 'description', 'position', 'is_active', 'meta_title', 'meta_description', 'created_at', 'updated_at', 'deleted_at'],
            'products' => ['id', 'uuid', 'category_id', 'category_record_type', 'name', 'slug', 'short_description', 'description', 'benefits', 'faq', 'brand', 'type', 'allows_customization', 'customization_instructions', 'sale_unit', 'content_quantity', 'content_unit', 'status', 'published_at', 'is_featured', 'meta_title', 'meta_description', 'indexable', 'created_at', 'updated_at', 'deleted_at'],
            'product_variants' => ['id', 'uuid', 'product_id', 'label', 'sku', 'barcode', 'combination_signature', 'used_at', 'sale_price', 'unit_cost', 'previous_price', 'tax_configuration', 'physical_stock', 'reserved_stock', 'quarantine_stock', 'low_stock_threshold', 'weight_kg', 'length_cm', 'width_cm', 'height_cm', 'is_active', 'position', 'created_at', 'updated_at', 'deleted_at'],
            'product_options' => ['id', 'uuid', 'product_id', 'parent_id', 'record_type', 'parent_record_type', 'name', 'identity_code', 'display_type', 'color_hex', 'position', 'created_at', 'updated_at', 'deleted_at'],
            'variant_option_values' => ['id', 'uuid', 'product_id', 'variant_id', 'option_id', 'value_id', 'option_record_type', 'value_record_type', 'created_at', 'updated_at'],
            'product_tags' => ['id', 'uuid', 'product_id', 'tag_id', 'tag_record_type', 'created_at', 'updated_at'],
            'product_promotions' => ['id', 'uuid', 'product_id', 'variant_id', 'sales_page_id', 'name', 'discount_type', 'value', 'minimum_quantity', 'started_at', 'ended_at', 'priority', 'is_active', 'created_at', 'updated_at', 'deleted_at'],
            'product_reviews' => ['id', 'uuid', 'product_id', 'visitor_id', 'order_item_id', 'moderated_by_id', 'display_name', 'note', 'comment', 'moderation_status', 'moderated_at', 'published_at', 'created_at', 'updated_at', 'deleted_at'],
            'visitors' => ['id', 'uuid', 'token_hash', 'first_visited_at', 'last_visited_at', 'expires_at', 'created_at', 'updated_at'],
            'visit_sessions' => ['id', 'uuid', 'visitor_id', 'started_at', 'last_activity_at', 'ended_at', 'entry_path', 'source', 'medium', 'campaign', 'referrer_host', 'device_type', 'created_at', 'updated_at'],
            'navigation_events' => ['id', 'uuid', 'session_id', 'product_id', 'variant_id', 'sales_page_id', 'content_page_id', 'cart_id', 'sales_page_kind', 'content_page_kind', 'type', 'path', 'quantity', 'occurred_at', 'received_at', 'created_at'],
            'carts' => ['id', 'uuid', 'visitor_id', 'status', 'last_activity_at', 'expires_at', 'converted_at', 'created_at', 'updated_at'],
            'cart_items' => ['id', 'uuid', 'cart_id', 'variant_id', 'product_id', 'sales_page_id', 'quantity', 'customization_text', 'customization_signature', 'created_at', 'updated_at'],
            'orders' => ['id', 'uuid', 'visitor_id', 'cart_id', 'original_session_id', 'original_sales_page_id', 'original_return_id', 'original_order_id', 'original_incident_id', 'current_revision_id', 'confirmed_revision_id', 'confirmation_owner_id', 'operationally_confirmed_by_id', 'original_sales_page_kind', 'unpaid_resend_slot', 'number', 'data_policy_version', 'data_notice_acknowledged_at', 'notice_text_hash', 'original_incident_quantity', 'replacement_reason', 'order_type', 'channel', 'commercial_status', 'validated_at', 'operationally_confirmed_at', 'submission_key', 'submission_hash', 'lock_version', 'retention_hold', 'retention_hold_reason', 'hold_review_at', 'created_at', 'updated_at'],
            'order_revisions' => ['id', 'uuid', 'order_id', 'author_id', 'free_shipping_rule_id', 'pickup_point_uuid', 'province_uuid', 'municipality_uuid', 'revision_number', 'currency', 'country_code', 'legal_seller_snapshot', 'shipping_tax_snapshot', 'reason', 'recipient_last_name', 'recipient_first_name', 'phone', 'secondary_phone', 'email', 'address', 'province_name', 'municipality_name', 'postal_code', 'delivery_mode', 'pickup_point_snapshot', 'catalog_subtotal', 'applied_subtotal', 'customer_shipping_fee', 'shipping_discount', 'shipping_charge_bearer', 'merchant_shipping_amount', 'order_total', 'return_cost_recovery_amount', 'return_cost_recovery_reason', 'amount_to_collect', 'customer_note', 'sales_terms_version', 'sales_terms_snapshot', 'created_at'],
            'order_items' => ['id', 'uuid', 'revision_id', 'variant_id', 'product_id', 'promotion_id', 'sales_page_id', 'product_name', 'variant_name', 'sku', 'options_snapshot', 'customization_text', 'quantity', 'catalog_unit_price', 'applied_unit_price', 'is_price_overridden', 'price_change_reason', 'price_origin', 'promotion_snapshot', 'unit_cost_snapshot', 'line_total', 'tax_snapshot', 'reservation_status', 'reserved_at', 'reservation_released_at', 'reservation_created_at', 'reservation_updated_at', 'created_at'],
            'order_history' => ['id', 'uuid', 'order_id', 'previous_revision_id', 'next_revision_id', 'actor_id', 'action', 'contact_outcome', 'next_callback_at', 'previous_status', 'new_status', 'changes', 'note', 'correlation_id', 'origin', 'created_at'],
            'stock_movements' => ['id', 'uuid', 'variant_id', 'order_item_id', 'return_item_id', 'actor_id', 'reversal_of_id', 'variant_sequence', 'type', 'physical_delta', 'reserved_delta', 'quarantine_delta', 'return_received_delta', 'return_restocked_delta', 'return_lost_delta', 'return_missing_delta', 'physical_before', 'physical_after', 'reserved_before', 'reserved_after', 'quarantine_before', 'quarantine_after', 'unit_cost_snapshot', 'loss_amount', 'operation_key', 'correlation_id', 'note', 'created_at'],
            'order_returns' => ['id', 'uuid', 'shipment_id', 'order_id', 'shipped_revision_id', 'received_by_id', 'reason', 'detail', 'status', 'requested_at', 'received_at', 'closed_at', 'created_at', 'updated_at'],
            'return_items' => ['id', 'uuid', 'return_id', 'order_item_id', 'shipped_revision_id', 'variant_id', 'inspected_by_id', 'expected_quantity', 'received_quantity', 'restocked_quantity', 'lost_quantity', 'quarantined_quantity', 'documented_missing_quantity', 'discrepancy_reason', 'unit_cost_snapshot', 'inspected_at', 'note', 'created_at', 'updated_at'],
            'shipping_providers' => ['id', 'uuid', 'user_id', 'carrier_account_id', 'type', 'name', 'phone', 'email', 'reference_configuration', 'last_synced_at', 'is_active', 'created_at', 'updated_at', 'deleted_at'],
            'shipping_rates' => ['id', 'uuid', 'provider_id', 'carrier_account_id', 'created_by_id', 'province_uuid', 'municipality_uuid', 'record_type', 'delivery_mode', 'service_type', 'amount', 'source', 'retrieved_at', 'starts_at', 'ends_at', 'is_active', 'provider_scope_id', 'municipality_scope_uuid', 'current_slot', 'created_at', 'updated_at', 'deleted_at'],
            'free_shipping_rules' => ['id', 'uuid', 'product_id', 'province_uuid', 'name', 'delivery_mode', 'minimum_cart_amount', 'started_at', 'ended_at', 'priority', 'is_active', 'created_at', 'updated_at', 'deleted_at'],
            'shipments' => ['id', 'uuid', 'order_id', 'shipped_revision_id', 'provider_id', 'label_media_id', 'assigned_by_id', 'pickup_point_uuid', 'delivery_mode', 'status', 'raw_external_status', 'tracking', 'merchant_reference', 'external_reference', 'cod_amount', 'estimated_cost', 'weight_kg', 'is_fragile', 'shipped_at', 'carrier_validated_at', 'delivered_at', 'last_synced_at', 'created_at', 'updated_at'],
            'shipment_events' => ['id', 'uuid', 'shipment_id', 'actor_id', 'logistics_status', 'financial_status', 'external_code', 'event_type', 'raw_external_activity', 'raw_external_status', 'adapter_version', 'sanitized_external_payload', 'payload_hash', 'payload_expires_at', 'payload_purged_at', 'reason', 'comment', 'station', 'courier_label', 'next_delivery_attempt_at', 'occurred_at', 'observed_at', 'source', 'deduplication_key', 'created_at'],
            'carrier_operations' => ['id', 'uuid', 'provider_id', 'shipment_id', 'order_id', 'return_id', 'revision_id', 'superseded_by_operation_id', 'triggered_by_id', 'type', 'operation_key', 'sanitized_request', 'encrypted_personal_request', 'request_hash', 'request_expires_at', 'request_purged_at', 'merchant_reference', 'adapter_version', 'technical_result', 'status', 'attempts_count', 'next_attempt_at', 'ended_at', 'sending_started_at', 'superseded_at', 'created_at', 'updated_at'],
            'carrier_operation_attempts' => ['id', 'uuid', 'operation_id', 'attempt_number', 'http_status_code', 'sanitized_response', 'payload_expires_at', 'payload_purged_at', 'error_code', 'duration_ms', 'started_at', 'ended_at', 'created_at'],
            'collections' => ['id', 'uuid', 'shipment_id', 'declared_status', 'expected_amount', 'declared_collected_amount', 'collected_at', 'payment_ready_at', 'declared_paid_at', 'source', 'reconciled_at', 'created_at', 'updated_at'],
            'remittance_statements' => ['id', 'uuid', 'provider_id', 'carrier_remittance_batch_id', 'validated_by_id', 'proof_media_id', 'reversal_of_id', 'number', 'external_reference', 'type', 'status', 'gross_amount', 'fee_amount', 'expected_net_amount', 'received_net_amount', 'declared_at', 'received_at', 'note', 'operation_key', 'reconciled_at', 'created_at', 'updated_at'],
            'carrier_settlement_lines' => ['id', 'uuid', 'provider_id', 'remittance_statement_id', 'collection_id', 'carrier_fee_id', 'receivable_id', 'shipment_id', 'replacement_order_id', 'proof_media_id', 'reversal_of_id', 'correction_of_id', 'operation_key', 'record_type', 'amount', 'fee_payment_mode', 'receivable_settlement_type', 'reason', 'external_reference', 'performed_at', 'created_at'],
            'expenses' => ['id', 'uuid', 'product_id', 'order_id', 'shipment_id', 'return_id', 'proof_media_id', 'author_id', 'reversal_of_id', 'correction_of_id', 'category', 'label', 'amount', 'expense_date', 'status', 'source', 'operation_key', 'cancelled_at', 'note', 'created_at', 'updated_at'],
            'customer_adjustments' => ['id', 'uuid', 'order_id', 'return_id', 'incident_id', 'credit_note_id', 'validated_by_id', 'proof_media_id', 'reversal_of_id', 'correction_of_id', 'compensated_quantity', 'amount_kind', 'type', 'amount', 'status', 'performed_at', 'reference', 'reason', 'operation_key', 'created_at', 'updated_at'],
            'order_documents' => ['id', 'uuid', 'order_id', 'revision_id', 'media_id', 'generated_by_id', 'number', 'document_version', 'issuer_snapshot', 'generated_at', 'created_at'],
            'activity_log' => ['id', 'uuid', 'operation_key', 'subject_id', 'causer_id', 'log_name', 'description', 'subject_type', 'event', 'causer_type', 'attribute_changes', 'properties', 'correlation_id', 'origin', 'performed_at', 'created_at', 'updated_at'],
            'carrier_fees' => ['id', 'uuid', 'shipment_id', 'provider_id', 'return_id', 'carrier_account_id', 'source_rate_id', 'proof_media_id', 'reversal_of_id', 'correction_of_id', 'rate_snapshot', 'source_rate_record_type', 'fee_type', 'payer', 'settlement_mode', 'amount', 'status', 'triggered_at', 'date_source', 'recognized_at', 'external_reference', 'operation_key', 'created_at', 'updated_at'],
            'carrier_receivables' => ['id', 'uuid', 'provider_id', 'carrier_fee_id', 'original_fee_payment_id', 'reversal_of_id', 'initial_amount', 'remaining_amount', 'reason', 'original_fee_payment_record_type', 'status', 'operation_key', 'recognized_at', 'settled_at', 'created_at', 'updated_at'],
            'collection_entries' => ['id', 'uuid', 'collection_id', 'verified_by_id', 'proof_media_id', 'reversal_of_id', 'correction_of_id', 'amount', 'collected_at', 'verified_at', 'reference', 'reason', 'operation_key', 'created_at'],
            'invoices' => ['id', 'uuid', 'order_id', 'revision_id', 'original_invoice_id', 'sequence_id', 'media_id', 'issued_by_id', 'incident_id', 'document_type', 'sequence_record_type', 'fiscal_year', 'sequence_number', 'snapshot_format_version', 'currency', 'number', 'status', 'seller_snapshot', 'client_snapshot', 'items_snapshot', 'totals_snapshot', 'issued_at', 'cancelled_at', 'cancellation_reason', 'operation_key', 'document_reason', 'created_at', 'updated_at'],
            'order_incidents' => ['id', 'uuid', 'order_id', 'shipment_id', 'shipped_revision_id', 'order_item_id', 'return_id', 'opened_by_id', 'validated_by_id', 'operation_key', 'affected_quantity', 'eligible_product_amount', 'eligible_shipping_amount', 'status', 'reason', 'validated_at', 'closed_at', 'created_at', 'updated_at'],
            'order_incident_details' => ['id', 'uuid', 'incident_id', 'author_id', 'type', 'quantity', 'reason', 'created_at', 'updated_at'],
            'billing_rules' => ['id', 'uuid', 'validated_by_id', 'record_type', 'document_type', 'fiscal_year', 'shop_prefix', 'next_number', 'sequence_slot', 'code', 'version', 'seller_profile_version', 'trigger_event', 'return_resend_rule', 'numbering_scope', 'parameters', 'policy_status', 'validation_reference', 'validated_at', 'effective_at', 'ends_at', 'created_at', 'updated_at'],
            'sales_terms_acceptances' => ['id', 'uuid', 'order_id', 'revision_id', 'operation_key', 'sales_terms_version', 'terms_hash', 'accepted_at', 'acceptance_mode', 'sanitized_proof', 'created_at'],
            'billing_obligations' => ['id', 'uuid', 'order_id', 'revision_id', 'billing_rule_id', 'original_invoice_id', 'invoice_id', 'operation_key', 'event_id', 'billing_rule_record_type', 'rule_snapshot', 'event_type', 'triggered_at', 'document_type', 'status', 'attempts_count', 'next_attempt_at', 'error_code', 'created_at', 'updated_at'],
            'commercial_corrections' => ['id', 'uuid', 'order_id', 'source_revision_id', 'incident_id', 'correction_of_id', 'actor_id', 'operation_key', 'correction_type', 'status', 'non_product_revenue_delta', 'non_product_kind', 'effective_at', 'recorded_at', 'reason', 'created_at'],
            'commercial_correction_lines' => ['id', 'uuid', 'correction_id', 'source_revision_id', 'order_item_id', 'affected_quantity', 'reference_sale_amount', 'revenue_delta', 'sold_cost_delta', 'detailed_reason', 'created_at'],
            'users' => ['id', 'uuid', 'central_user_uuid', 'email', 'last_name', 'first_name', 'password', 'phone', 'email_verified_at', 'locale', 'status', 'membership_status', 'joined_at', 'last_login_at', 'remember_token', 'created_at', 'updated_at', 'deleted_at'],
            'permissions' => ['id', 'uuid', 'name', 'guard_name', 'label', 'feature_code', 'created_at', 'updated_at'],
            'roles' => ['id', 'uuid', 'super_admin_slot', 'permission_signature', 'name', 'guard_name', 'label', 'is_system', 'is_protected', 'is_super_admin', 'permission_version', 'created_at', 'updated_at'],
            'role_has_permissions' => ['permission_id', 'role_id', 'duration_days'],
            'model_has_roles' => ['role_id', 'model_type', 'model_id', 'assigned_at'],
            'model_has_permissions' => ['permission_id', 'model_type', 'model_id', 'assigned_at', 'expires_at'],
            'team_invitations' => ['id', 'uuid', 'initial_role_id', 'invited_by_id', 'token_hash', 'email', 'role_permission_version', 'expires_at', 'accepted_at', 'revoked_at', 'created_at', 'updated_at'],
            'contact_verifications' => ['id', 'uuid', 'user_id', 'channel', 'normalized_destination', 'code_hash', 'expires_at', 'attempts_count', 'consumed_at', 'created_at', 'updated_at'],
            'carrier_accounts' => ['id', 'uuid', 'created_by_id', 'carrier_uuid', 'label', 'adapter', 'external_account_id', 'api_url', 'encrypted_api_credentials', 'encryption_key_version', 'is_active', 'last_synced_at', 'created_at', 'updated_at'],
            'carrier_remittance_batches' => ['id', 'uuid', 'carrier_account_id', 'proof_media_id', 'reversal_of_id', 'validated_by_id', 'operation_key', 'external_reference', 'reported_account_net_amount', 'computed_shop_net_amount', 'verified_net_amount', 'status', 'received_at', 'created_at', 'updated_at'],
        ];
        foreach ($this->checks() as [$table, $name, $expression]) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");

                continue;
            }

            $expression = str_replace('CHAR_LENGTH(', 'LENGTH(', $expression);
            $expression = str_replace("JSON_TYPE(payload) = 'OBJECT'", "json_type(payload) = 'object'", $expression);
            $expression = str_replace('expires_at <= DATE_ADD(assigned_at, INTERVAL 9999 DAY)', 'julianday(expires_at) - julianday(assigned_at) <= 9999', $expression);
            $pattern = '/(?<![A-Za-z0-9_])`?('.implode('|', $columns[$table]).')`?(?![A-Za-z0-9_])/';
            $expression = preg_replace_callback($pattern, fn (array $match): string => 'NEW.`'.$match[1].'`', $expression);

            foreach (['insert' => 'INSERT', 'update' => 'UPDATE'] as $suffix => $event) {
                DB::statement("CREATE TRIGGER {$name}_{$suffix} BEFORE {$event} ON `{$table}` WHEN NOT ({$expression}) BEGIN SELECT RAISE(ABORT, 'Documented constraint: {$name}'); END");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->checks() as [$table, $name]) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                DB::statement("DROP TRIGGER IF EXISTS {$name}_insert");
                DB::statement("DROP TRIGGER IF EXISTS {$name}_update");
            } else {
                DB::statement("ALTER TABLE `{$table}` DROP CHECK `{$name}`");
            }
        }
    }

    /**
     * @return list<array{string, string, string}>
     */
    private function checks(): array
    {
        return [
            ['shop_addresses', 'ck_shop_addresses_cbccd3efcf', 'record_type IN (1, 2)'],
            ['shop_addresses', 'ck_shop_addresses_4a4d821ee4', 'position >= 0 AND JSON_TYPE(payload) = \'OBJECT\''],
            ['shop_addresses', 'ck_shop_addresses_4c7351308c', '(record_type = 1 AND shop_address_id IS NULL AND province_uuid IS NOT NULL AND municipality_uuid IS NOT NULL AND label IS NOT NULL AND CHAR_LENGTH(TRIM(label)) > 0) OR (record_type = 2 AND province_uuid IS NULL AND municipality_uuid IS NULL AND is_primary = 0)'],
            ['content_pages', 'ck_content_pages_d723ab0c74', 'page_kind IN (1, 2)'],
            ['content_pages', 'ck_content_pages_20f8a59249', '(page_kind = 1 AND product_id IS NULL AND type IS NOT NULL AND CHAR_LENGTH(TRIM(type)) > 0 AND canonical_url IS NULL) OR (page_kind = 2 AND product_id IS NOT NULL AND type IS NULL)'],
            ['media', 'ck_media_b762576c60', 'visibility IN (1, 2)'],
            ['media', 'ck_media_9039b28f88', 'position >= 0'],
            ['categories', 'ck_categories_cbccd3efcf', 'record_type IN (1, 2)'],
            ['categories', 'ck_categories_8ee34b862b', 'record_type <> 2 OR parent_id IS NULL'],
            ['products', 'ck_products_74be1e9e32', 'type IN (1, 2)'],
            ['products', 'ck_products_7fa97793e0', 'status IN (1, 2, 3)'],
            ['product_options', 'ck_product_options_cbccd3efcf', 'record_type IN (1, 2)'],
            ['product_options', 'ck_product_options_69bc03a819', 'display_type IS NULL OR display_type IN (1, 2, 3)'],
            ['product_options', 'ck_product_options_85d78dec00', 'CHAR_LENGTH(TRIM(name)) > 0'],
            ['product_options', 'ck_product_options_173662fe0f', '(record_type = 1 AND parent_id IS NULL AND identity_code IS NULL AND display_type IS NOT NULL AND display_type IN (1,2,3) AND color_hex IS NULL) OR (record_type = 2 AND parent_id IS NOT NULL AND identity_code IS NOT NULL AND CHAR_LENGTH(TRIM(identity_code)) > 0 AND display_type IS NULL)'],
            ['product_promotions', 'ck_product_promotions_a33b1bf065', 'discount_type IN (1, 2, 3)'],
            ['product_promotions', 'ck_product_promotions_01a1bbed62', 'minimum_quantity >= 1 AND value >= 0 AND (discount_type <> 1 OR value <= 100)'],
            ['product_reviews', 'ck_product_reviews_48b4e4fdb2', 'moderation_status IN (1, 2, 3, 4)'],
            ['product_reviews', 'ck_product_reviews_cb7681fbc4', 'note BETWEEN 1 AND 5'],
            ['visit_sessions', 'ck_visit_sessions_21a8950eba', 'device_type IS NULL OR device_type IN (1, 2, 3, 4)'],
            ['carts', 'ck_carts_24b1d83b73', 'status IN (1, 2, 3, 4)'],
            ['orders', 'ck_orders_c2a61bf48e', 'order_type IN (1, 2, 4)'],
            ['orders', 'ck_orders_c46158c9c6', 'channel IN (1, 2)'],
            ['orders', 'ck_orders_29eeb93249', 'commercial_status IN (1, 2, 5)'],
            ['orders', 'ck_orders_1a0a7f3619', '(confirmed_revision_id IS NULL AND validated_at IS NULL) OR (confirmed_revision_id IS NOT NULL AND validated_at IS NOT NULL)'],
            ['orders', 'ck_orders_0cbe16a436', 'commercial_status <> 2 OR (confirmed_revision_id IS NOT NULL AND validated_at IS NOT NULL)'],
            ['orders', 'ck_orders_836e3c99e9', '(order_type = 1 AND original_order_id IS NULL AND original_return_id IS NULL AND original_incident_id IS NULL AND original_incident_quantity IS NULL) OR (order_type = 2 AND original_order_id IS NOT NULL AND original_incident_id IS NOT NULL AND original_incident_quantity IS NOT NULL AND original_incident_quantity > 0) OR (order_type = 4 AND original_order_id IS NOT NULL AND original_return_id IS NOT NULL)'],
            ['orders', 'ck_orders_2a66eb60d4', '(original_incident_id IS NULL AND original_incident_quantity IS NULL) OR (original_incident_id IS NOT NULL AND original_incident_quantity IS NOT NULL AND original_incident_quantity > 0)'],
            ['order_revisions', 'ck_order_revisions_d4bb3f99ff', 'delivery_mode IN (1, 2)'],
            ['order_revisions', 'ck_order_revisions_e85e1d3359', 'shipping_charge_bearer IN (1, 2, 3)'],
            ['order_revisions', 'ck_order_revisions_150e82100d', 'revision_number > 0'],
            ['order_revisions', 'ck_order_revisions_a6e668f934', 'catalog_subtotal >= 0 AND applied_subtotal >= 0 AND customer_shipping_fee >= 0 AND shipping_discount >= 0 AND shipping_discount <= customer_shipping_fee AND merchant_shipping_amount >= 0 AND return_cost_recovery_amount >= 0 AND order_total >= 0 AND amount_to_collect = order_total AND order_total = applied_subtotal + customer_shipping_fee - shipping_discount + return_cost_recovery_amount'],
            ['order_revisions', 'ck_order_revisions_7cce9f903d', 'return_cost_recovery_amount = 0 OR (return_cost_recovery_reason IS NOT NULL AND CHAR_LENGTH(TRIM(return_cost_recovery_reason)) > 0)'],
            ['order_revisions', 'ck_order_revisions_04129ae066', '(delivery_mode = 1 AND pickup_point_uuid IS NULL AND pickup_point_snapshot IS NULL) OR (delivery_mode = 2 AND pickup_point_uuid IS NOT NULL AND pickup_point_snapshot IS NOT NULL)'],
            ['order_items', 'ck_order_items_d35bcee5a8', 'price_origin IN (1, 2, 3)'],
            ['order_items', 'ck_order_items_565fd683da', 'reservation_status IS NULL OR reservation_status IN (1, 2, 3)'],
            ['order_items', 'ck_order_items_121bbc4df0', 'quantity > 0 AND catalog_unit_price >= 0 AND applied_unit_price >= 0 AND unit_cost_snapshot >= 0 AND line_total = ROUND(quantity * applied_unit_price, 2)'],
            ['order_items', 'ck_order_items_038c49efa6', 'price_origin <> 3 OR (is_price_overridden = 1 AND price_change_reason IS NOT NULL AND CHAR_LENGTH(TRIM(price_change_reason)) > 0 AND promotion_id IS NULL)'],
            ['order_items', 'ck_order_items_b1462d1a6c', '(reservation_status IS NULL AND reserved_at IS NULL AND reservation_released_at IS NULL AND reservation_created_at IS NULL AND reservation_updated_at IS NULL) OR (reservation_status IS NOT NULL AND reservation_status IN (1,2,3) AND reserved_at IS NOT NULL AND reservation_created_at IS NOT NULL AND reservation_updated_at IS NOT NULL AND ((reservation_status = 2 AND reservation_released_at IS NOT NULL) OR (reservation_status IN (1,3) AND reservation_released_at IS NULL)))'],
            ['order_history', 'ck_order_history_616f0b9b05', 'contact_outcome IS NULL OR contact_outcome IN (1, 2, 3, 4, 5)'],
            ['order_history', 'ck_order_history_087d067085', 'previous_status IS NULL OR previous_status IN (1, 2, 5)'],
            ['order_history', 'ck_order_history_e985cb7e6c', 'new_status IS NULL OR new_status IN (1, 2, 5)'],
            ['order_history', 'ck_order_history_762cba2a33', 'origin IN (1, 2, 3, 4)'],
            ['stock_movements', 'ck_stock_movements_45b46352b9', 'type IN (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11)'],
            ['stock_movements', 'ck_stock_movements_c7ec7c1fde', 'variant_sequence > 0 AND physical_before >= 0 AND physical_after >= 0 AND reserved_before >= 0 AND reserved_after >= 0 AND quarantine_before >= 0 AND quarantine_after >= 0 AND reserved_after <= physical_after AND physical_after = physical_before + physical_delta AND reserved_after = reserved_before + reserved_delta AND quarantine_after = quarantine_before + quarantine_delta'],
            ['order_returns', 'ck_order_returns_fa22e728b3', 'reason IN (1, 2, 3, 4, 5, 6)'],
            ['order_returns', 'ck_order_returns_5648823bc2', 'status IN (1, 2, 3, 4, 5, 6)'],
            ['shipping_providers', 'ck_shipping_providers_b217f304d0', 'type IN (1, 2, 3)'],
            ['shipping_providers', 'ck_shipping_providers_b19c289e66', '(type = 1 AND carrier_account_id IS NOT NULL) OR (type IN (2,3) AND carrier_account_id IS NULL AND reference_configuration IS NULL)'],
            ['shipping_rates', 'ck_shipping_rates_43077a12e1', 'record_type IN (1, 2, 3)'],
            ['shipping_rates', 'ck_shipping_rates_7e434ba4b2', 'delivery_mode IS NULL OR delivery_mode IN (1, 2)'],
            ['shipping_rates', 'ck_shipping_rates_2ef5f84da4', 'service_type IS NULL OR service_type IN (1, 2)'],
            ['shipping_rates', 'ck_shipping_rates_6bf2cd7466', 'source IS NULL OR source IN (1, 2)'],
            ['shipping_rates', 'ck_shipping_rates_32e629f93a', 'amount >= 0'],
            ['shipping_rates', 'ck_shipping_rates_3a9ed07042', '(record_type = 1 AND province_uuid IS NOT NULL AND delivery_mode IS NOT NULL AND service_type IS NOT NULL AND service_type = 1 AND provider_id IS NULL AND carrier_account_id IS NULL AND created_by_id IS NULL AND source IS NULL AND retrieved_at IS NULL AND starts_at IS NULL AND ends_at IS NULL) OR (record_type = 2 AND provider_id IS NOT NULL AND province_uuid IS NOT NULL AND delivery_mode IS NOT NULL AND service_type IS NOT NULL AND source IS NOT NULL AND carrier_account_id IS NULL AND created_by_id IS NULL AND starts_at IS NULL AND ends_at IS NULL) OR (record_type = 3 AND carrier_account_id IS NOT NULL AND created_by_id IS NOT NULL AND source IS NOT NULL AND starts_at IS NOT NULL AND provider_id IS NULL AND province_uuid IS NULL AND municipality_uuid IS NULL AND delivery_mode IS NULL AND service_type IS NULL AND retrieved_at IS NULL AND deleted_at IS NULL)'],
            ['shipping_rates', 'ck_shipping_rates_cd8e300e14', 'ends_at IS NULL OR (starts_at IS NOT NULL AND ends_at > starts_at)'],
            ['free_shipping_rules', 'ck_free_shipping_rules_7e434ba4b2', 'delivery_mode IS NULL OR delivery_mode IN (1, 2)'],
            ['shipments', 'ck_shipments_d4bb3f99ff', 'delivery_mode IN (1, 2)'],
            ['shipments', 'ck_shipments_d79711973a', 'status IN (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11)'],
            ['shipments', 'ck_shipments_f6025185b0', '(delivery_mode = 1 AND pickup_point_uuid IS NULL) OR (delivery_mode = 2 AND pickup_point_uuid IS NOT NULL)'],
            ['shipment_events', 'ck_shipment_events_66336a6c15', 'logistics_status IS NULL OR logistics_status IN (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11)'],
            ['shipment_events', 'ck_shipment_events_fb5026231d', 'financial_status IS NULL OR financial_status IN (1, 2, 3, 4, 5, 6, 7)'],
            ['shipment_events', 'ck_shipment_events_2799f40951', 'source IN (1, 2, 3)'],
            ['carrier_operations', 'ck_carrier_operations_c35f401b6b', 'type IN (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12)'],
            ['carrier_operations', 'ck_carrier_operations_e38744cd9b', 'status IN (1, 2, 3, 4, 5, 6, 7, 8)'],
            ['carrier_operations', 'ck_carrier_operations_8a9891f71d', 'attempts_count >= 0'],
            ['collections', 'ck_collections_f86ea7204d', 'declared_status IN (1, 2, 3, 4, 5, 6, 7)'],
            ['collections', 'ck_collections_4cd585b5b2', 'expected_amount >= 0 AND (declared_collected_amount IS NULL OR declared_collected_amount >= 0)'],
            ['remittance_statements', 'ck_remittance_statements_6d22a845c0', 'type IN (1, 2, 3, 4, 5)'],
            ['remittance_statements', 'ck_remittance_statements_5648823bc2', 'status IN (1, 2, 3, 4, 5, 6)'],
            ['remittance_statements', 'ck_remittance_statements_b6c681f7ca', 'expected_net_amount = gross_amount - fee_amount'],
            ['carrier_settlement_lines', 'ck_carrier_settlement_lines_20dcc1d4c8', 'record_type IN (1, 2, 3, 4)'],
            ['carrier_settlement_lines', 'ck_carrier_settlement_lines_5adf21351a', 'fee_payment_mode IS NULL OR fee_payment_mode IN (1, 2, 3, 4)'],
            ['carrier_settlement_lines', 'ck_carrier_settlement_lines_6587316d76', 'receivable_settlement_type IS NULL OR receivable_settlement_type IN (1, 2, 3, 4)'],
            ['carrier_settlement_lines', 'ck_carrier_settlement_lines_2cfb962f81', '(reversal_of_id IS NULL AND amount > 0) OR (reversal_of_id IS NOT NULL AND amount < 0)'],
            ['carrier_settlement_lines', 'ck_carrier_settlement_lines_7a4a411e63', 'carrier_fee_id IS NULL OR shipment_id IS NOT NULL'],
            ['carrier_settlement_lines', 'ck_carrier_settlement_lines_34d48037c9', '(record_type = 1 AND collection_id IS NOT NULL AND shipment_id IS NOT NULL AND remittance_statement_id IS NOT NULL AND carrier_fee_id IS NULL AND receivable_id IS NULL AND replacement_order_id IS NULL AND proof_media_id IS NULL AND fee_payment_mode IS NULL AND receivable_settlement_type IS NULL AND reason IS NULL AND external_reference IS NULL AND performed_at IS NULL) OR (record_type = 2 AND carrier_fee_id IS NOT NULL AND shipment_id IS NOT NULL AND remittance_statement_id IS NOT NULL AND fee_payment_mode IS NOT NULL AND fee_payment_mode IN (2,3) AND collection_id IS NULL AND receivable_id IS NULL AND replacement_order_id IS NULL AND proof_media_id IS NULL AND receivable_settlement_type IS NULL AND reason IS NULL AND external_reference IS NULL AND performed_at IS NULL) OR (record_type = 3 AND receivable_id IS NOT NULL AND receivable_settlement_type IS NOT NULL AND performed_at IS NOT NULL AND collection_id IS NULL AND replacement_order_id IS NULL AND fee_payment_mode IS NULL AND reason IS NULL) OR (record_type = 4 AND shipment_id IS NOT NULL AND remittance_statement_id IS NOT NULL AND reason IS NOT NULL AND external_reference IS NOT NULL AND collection_id IS NULL AND carrier_fee_id IS NULL AND receivable_id IS NULL AND fee_payment_mode IS NULL AND receivable_settlement_type IS NULL AND performed_at IS NULL)'],
            ['carrier_settlement_lines', 'ck_carrier_settlement_lines_8cede90479', 'record_type <> 3 OR ((carrier_fee_id IS NULL AND shipment_id IS NULL) OR (carrier_fee_id IS NOT NULL AND shipment_id IS NOT NULL))'],
            ['carrier_settlement_lines', 'ck_carrier_settlement_lines_044f31e656', 'record_type <> 3 OR receivable_settlement_type <> 1 OR (remittance_statement_id IS NOT NULL AND proof_media_id IS NOT NULL AND carrier_fee_id IS NULL)'],
            ['carrier_settlement_lines', 'ck_carrier_settlement_lines_6c0688ee99', 'record_type <> 3 OR receivable_settlement_type <> 2 OR carrier_fee_id IS NOT NULL'],
            ['carrier_settlement_lines', 'ck_carrier_settlement_lines_22092f3da9', 'record_type <> 3 OR receivable_settlement_type <> 3 OR remittance_statement_id IS NOT NULL'],
            ['expenses', 'ck_expenses_24b1d83b73', 'status IN (1, 2, 3, 4)'],
            ['expenses', 'ck_expenses_2cfb962f81', '(reversal_of_id IS NULL AND amount > 0) OR (reversal_of_id IS NOT NULL AND amount < 0)'],
            ['customer_adjustments', 'ck_customer_adjustments_b377d3055f', 'amount_kind IN (1, 2, 3)'],
            ['customer_adjustments', 'ck_customer_adjustments_b217f304d0', 'type IN (1, 2, 3)'],
            ['customer_adjustments', 'ck_customer_adjustments_757dbec8d7', 'status IN (1, 2, 3, 4, 5)'],
            ['customer_adjustments', 'ck_customer_adjustments_2cfb962f81', '(reversal_of_id IS NULL AND amount > 0) OR (reversal_of_id IS NOT NULL AND amount < 0)'],
            ['activity_log', 'ck_activity_log_762cba2a33', 'origin IN (1, 2, 3, 4)'],
            ['activity_log', 'ck_activity_log_6a73f15349', '(subject_type IS NULL AND subject_id IS NULL) OR (subject_type IS NOT NULL AND subject_id IS NOT NULL)'],
            ['activity_log', 'ck_activity_log_04eb33cd66', '(causer_type IS NULL AND causer_id IS NULL) OR (causer_type IS NOT NULL AND causer_id IS NOT NULL)'],
            ['activity_log', 'ck_activity_log_223500de31', 'log_name IS NULL OR log_name <> \'privacy\' OR performed_at IS NOT NULL'],
            ['carrier_fees', 'ck_carrier_fees_b2dfe7a5c9', 'fee_type IN (1, 2, 3, 4, 5, 6)'],
            ['carrier_fees', 'ck_carrier_fees_2933c18fd6', 'payer IN (1, 2, 3, 4)'],
            ['carrier_fees', 'ck_carrier_fees_cfe9b872b9', 'settlement_mode IN (1, 2, 3, 4)'],
            ['carrier_fees', 'ck_carrier_fees_757dbec8d7', 'status IN (1, 2, 3, 4, 5)'],
            ['carrier_fees', 'ck_carrier_fees_09636ef52f', '(reversal_of_id IS NULL AND (amount > 0 OR (fee_type = 2 AND amount = 0 AND rate_snapshot IS NOT NULL))) OR (reversal_of_id IS NOT NULL AND (amount < 0 OR (fee_type = 2 AND amount = 0 AND rate_snapshot IS NOT NULL)))'],
            ['carrier_fees', 'ck_carrier_fees_ef8e97d3fa', 'source_rate_id IS NULL OR carrier_account_id IS NOT NULL'],
            ['carrier_receivables', 'ck_carrier_receivables_757dbec8d7', 'status IN (1, 2, 3, 4, 5)'],
            ['carrier_receivables', 'ck_carrier_receivables_2d5d3fad6e', '(reversal_of_id IS NULL AND initial_amount > 0 AND remaining_amount >= 0 AND remaining_amount <= initial_amount) OR (reversal_of_id IS NOT NULL AND initial_amount < 0 AND remaining_amount = 0)'],
            ['invoices', 'ck_invoices_6ed49a29e3', 'document_type IN (1, 2)'],
            ['invoices', 'ck_invoices_24b1d83b73', 'status IN (1, 2, 3, 4)'],
            ['invoices', 'ck_invoices_204a0bea84', '(sequence_id IS NULL AND fiscal_year IS NULL AND sequence_number IS NULL AND number IS NULL) OR (sequence_id IS NOT NULL AND fiscal_year IS NOT NULL AND sequence_number IS NOT NULL AND number IS NOT NULL AND sequence_number > 0)'],
            ['invoices', 'ck_invoices_5730245384', 'status NOT IN (2,4) OR (sequence_id IS NOT NULL AND fiscal_year IS NOT NULL AND sequence_number IS NOT NULL AND number IS NOT NULL)'],
            ['invoices', 'ck_invoices_d8e5eabe59', 'status <> 2 OR (media_id IS NOT NULL AND issued_at IS NOT NULL)'],
            ['invoices', 'ck_invoices_fc86a60810', 'status <> 3 OR (cancelled_at IS NOT NULL AND cancellation_reason IS NOT NULL AND CHAR_LENGTH(TRIM(cancellation_reason)) > 0)'],
            ['invoices', 'ck_invoices_6a6e395233', '(document_type = 1 AND original_invoice_id IS NULL) OR (document_type = 2 AND original_invoice_id IS NOT NULL AND document_reason IS NOT NULL AND CHAR_LENGTH(TRIM(document_reason)) > 0)'],
            ['order_incidents', 'ck_order_incidents_5648823bc2', 'status IN (1, 2, 3, 4, 5, 6)'],
            ['order_incidents', 'ck_order_incidents_b4842e4640', 'affected_quantity > 0 AND eligible_product_amount >= 0 AND eligible_shipping_amount >= 0'],
            ['order_incident_details', 'ck_order_incident_details_48ce8520fc', 'type IN (1, 2, 3, 4, 5, 6)'],
            ['order_incident_details', 'ck_order_incident_details_c2363b9cc2', 'quantity > 0 AND CHAR_LENGTH(TRIM(reason)) > 0'],
            ['billing_rules', 'ck_billing_rules_cbccd3efcf', 'record_type IN (1, 2)'],
            ['billing_rules', 'ck_billing_rules_25f4ff1481', 'document_type IS NULL OR document_type IN (1, 2)'],
            ['billing_rules', 'ck_billing_rules_6dac4dd1df', 'policy_status IS NULL OR policy_status IN (1, 2, 3, 4)'],
            ['billing_rules', 'ck_billing_rules_5cb41c211e', '(record_type = 1 AND document_type IS NOT NULL AND fiscal_year IS NOT NULL AND shop_prefix IS NOT NULL AND next_number IS NOT NULL AND next_number > 0 AND code IS NULL AND version IS NULL AND trigger_event IS NULL AND numbering_scope IS NULL AND parameters IS NULL AND policy_status IS NULL AND return_resend_rule IS NULL AND validated_by_id IS NULL AND validated_at IS NULL AND validation_reference IS NULL AND effective_at IS NULL AND ends_at IS NULL) OR (record_type = 2 AND document_type IS NULL AND fiscal_year IS NULL AND shop_prefix IS NULL AND next_number IS NULL AND code IS NOT NULL AND version IS NOT NULL AND trigger_event IS NOT NULL AND numbering_scope IS NOT NULL AND parameters IS NOT NULL AND policy_status IS NOT NULL AND return_resend_rule IS NOT NULL AND version > 0 AND numbering_scope = \'shop\')'],
            ['billing_rules', 'ck_billing_rules_64506bf607', 'policy_status IS NULL OR policy_status NOT IN (2,3) OR (validated_by_id IS NOT NULL AND validated_at IS NOT NULL AND validation_reference IS NOT NULL AND seller_profile_version IS NOT NULL)'],
            ['billing_rules', 'ck_billing_rules_7b93dc0447', 'policy_status IS NULL OR policy_status <> 3 OR effective_at IS NOT NULL'],
            ['billing_rules', 'ck_billing_rules_4b77689abb', 'ends_at IS NULL OR (effective_at IS NOT NULL AND ends_at > effective_at)'],
            ['sales_terms_acceptances', 'ck_sales_terms_acceptances_e3e8885ec1', 'acceptance_mode IN (1, 2)'],
            ['billing_obligations', 'ck_billing_obligations_6ed49a29e3', 'document_type IN (1, 2)'],
            ['billing_obligations', 'ck_billing_obligations_757dbec8d7', 'status IN (1, 2, 3, 4, 5)'],
            ['billing_obligations', 'ck_billing_obligations_94d6fb8bde', 'attempts_count >= 0 AND (status <> 3 OR invoice_id IS NOT NULL)'],
            ['billing_obligations', 'ck_billing_obligations_ef15b128ff', '(document_type = 1 AND original_invoice_id IS NULL) OR (document_type = 2 AND original_invoice_id IS NOT NULL)'],
            ['commercial_corrections', 'ck_commercial_corrections_b9b48b20e3', 'correction_type IN (1, 2, 4, 5, 6, 7)'],
            ['commercial_corrections', 'ck_commercial_corrections_24b1d83b73', 'status IN (1, 2, 3, 4)'],
            ['commercial_corrections', 'ck_commercial_corrections_4c6b56fe7e', 'non_product_kind IN (1, 2, 3, 4)'],
            ['commercial_corrections', 'ck_commercial_corrections_3d42799dd0', '(non_product_kind = 1 AND non_product_revenue_delta = 0) OR (non_product_kind <> 1 AND non_product_revenue_delta <> 0)'],
            ['users', 'ck_users_24b1d83b73', 'status IN (1, 2, 3, 4)'],
            ['users', 'ck_users_d2898f856e', 'membership_status IN (1, 2, 3, 4)'],
            ['users', 'ck_users_1a606ba59a', 'membership_status <> 1 OR joined_at IS NOT NULL'],
            ['contact_verifications', 'ck_contact_verifications_40f36046fc', 'channel IN (1, 2, 3)'],
            ['contact_verifications', 'ck_contact_verifications_8a9891f71d', 'attempts_count >= 0'],
            ['carrier_remittance_batches', 'ck_carrier_remittance_batches_7fa97793e0', 'status IN (1, 2, 3)'],
            ['carrier_remittance_batches', 'ck_carrier_remittance_batches_c3e7002eaa', 'status <> 2 OR (verified_net_amount IS NOT NULL AND validated_by_id IS NOT NULL AND received_at IS NOT NULL AND proof_media_id IS NOT NULL)'],
            ['permissions', 'ck_permissions_6bec899416', 'guard_name = \'tenant\''],
            ['permissions', 'ck_permissions_83cb87a4fe', 'name NOT LIKE \'saas.%\''],
            ['roles', 'ck_roles_6bec899416', 'guard_name = \'tenant\''],
            ['roles', 'ck_roles_85d78dec00', 'CHAR_LENGTH(TRIM(name)) > 0'],
            ['role_has_permissions', 'ck_role_has_permissions_61a9c661de', 'duration_days BETWEEN 1 AND 9999'],
            ['model_has_roles', 'ck_model_has_roles_1698446bcf', 'model_type = \'shop_user\''],
            ['model_has_permissions', 'ck_model_has_permissions_1698446bcf', 'model_type = \'shop_user\''],
            ['model_has_permissions', 'ck_model_has_permissions_a55ae2c2be', 'expires_at > assigned_at AND expires_at <= DATE_ADD(assigned_at, INTERVAL 9999 DAY)'],
            ['shop', 'ck_shop_160ec0da2d', 'singleton = 1'],
            ['product_variants', 'ck_product_variants_ea4c5d3e8b', 'physical_stock >= 0 AND reserved_stock >= 0 AND quarantine_stock >= 0 AND reserved_stock <= physical_stock'],
            ['product_variants', 'ck_product_variants_707ee5a007', 'sale_price >= 0 AND unit_cost >= 0 AND low_stock_threshold >= 0 AND (previous_price IS NULL OR previous_price >= 0)'],
            ['navigation_events', 'ck_navigation_events_12ef73d372', 'sales_page_id IS NULL OR product_id IS NOT NULL'],
            ['cart_items', 'ck_cart_items_82113324c8', 'quantity > 0'],
            ['return_items', 'ck_return_items_b495b66fe2', 'expected_quantity > 0 AND received_quantity >= 0 AND restocked_quantity >= 0 AND lost_quantity >= 0 AND quarantined_quantity >= 0 AND documented_missing_quantity >= 0 AND received_quantity <= expected_quantity AND received_quantity = restocked_quantity + lost_quantity + quarantined_quantity AND received_quantity + documented_missing_quantity <= expected_quantity'],
            ['return_items', 'ck_return_items_0a93295568', 'documented_missing_quantity = 0 OR (discrepancy_reason IS NOT NULL AND CHAR_LENGTH(TRIM(discrepancy_reason)) > 0)'],
            ['carrier_operation_attempts', 'ck_carrier_operation_attempts_d7e85a4503', 'attempt_number > 0 AND duration_ms >= 0'],
            ['collection_entries', 'ck_collection_entries_2cfb962f81', '(reversal_of_id IS NULL AND amount > 0) OR (reversal_of_id IS NOT NULL AND amount < 0)'],
            ['commercial_correction_lines', 'ck_commercial_correction_lines_5deaab7edb', 'affected_quantity > 0 AND reference_sale_amount >= 0'],
        ];
    }
};
