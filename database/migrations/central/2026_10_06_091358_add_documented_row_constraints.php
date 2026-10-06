<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'countries' => ['id', 'uuid', 'code', 'name_fr', 'name_en', 'name_ar', 'is_active', 'created_at', 'updated_at'],
            'users' => ['id', 'uuid', 'country_id', 'legal_verified_by_id', 'email', 'last_name', 'first_name', 'password', 'phone', 'email_verified_at', 'phone_verified_at', 'whatsapp_verified_at', 'legal_form', 'activity_nature', 'nif', 'nis', 'registration_number', 'artisan_card_number', 'legal_address', 'share_capital', 'legal_profile_version', 'legal_verification_status', 'legal_verified_at', 'locale', 'status', 'last_login_at', 'remember_token', 'created_at', 'updated_at', 'deleted_at'],
            'tenants' => ['id', 'uuid', 'user_id', 'slug', 'document_prefix', 'internal_label', 'shop_name', 'profile_version', 'creation_key', 'creation_hash', 'status', 'is_primary', 'activation_priority', 'over_quota_since_at', 'data', 'schema_version', 'provisioned_at', 'created_at', 'updated_at', 'deleted_at'],
            'domains' => ['id', 'uuid', 'tenant_id', 'domain', 'type', 'is_primary', 'verification_status', 'verified_at', 'certificate_status', 'created_at', 'updated_at', 'deleted_at'],
            'contact_verifications' => ['id', 'uuid', 'user_id', 'channel', 'normalized_destination', 'code_hash', 'expires_at', 'attempts_count', 'consumed_at', 'created_at', 'updated_at'],
            'features' => ['id', 'uuid', 'code', 'name', 'value_type', 'unit', 'quota_scope', 'period', 'is_active', 'created_at', 'updated_at', 'deleted_at'],
            'permissions' => ['id', 'uuid', 'name', 'guard_name', 'label', 'feature_code', 'created_at', 'updated_at'],
            'roles' => ['id', 'uuid', 'super_admin_slot', 'permission_signature', 'name', 'guard_name', 'label', 'is_system', 'is_protected', 'is_super_admin', 'permission_version', 'created_at', 'updated_at'],
            'role_has_permissions' => ['permission_id', 'role_id', 'duration_days'],
            'model_has_roles' => ['role_id', 'model_type', 'model_id', 'assigned_at'],
            'model_has_permissions' => ['permission_id', 'model_type', 'model_id', 'assigned_at', 'expires_at'],
            'admin_restrictions' => ['id', 'uuid', 'admin_id', 'permission_id', 'target_tenant_id', 'target_user_id', 'target_role_id', 'created_by_id', 'effect', 'status', 'started_at', 'ended_at', 'normalized_target_type', 'normalized_target_id', 'active_slot', 'expires_at', 'created_at', 'updated_at', 'deleted_at'],
            'plans' => ['id', 'uuid', 'code', 'version', 'name', 'description', 'monthly_price', 'annual_price', 'is_active', 'created_at', 'updated_at', 'deleted_at'],
            'plan_features' => ['id', 'uuid', 'plan_id', 'feature_id', 'is_active', 'limit', 'created_at', 'updated_at'],
            'subscriptions' => ['id', 'uuid', 'user_id', 'parent_subscription_id', 'tenant_id', 'plan_id', 'assigned_by_id', 'installment_number', 'operation_key', 'record_type', 'parent_record_type', 'status', 'period', 'agreed_amount', 'started_at', 'period_starts_at', 'period_ends_at', 'trial_ends_at', 'ended_at', 'auto_renew', 'installment_amount', 'due_at', 'installment_status', 'active_owner_slot', 'created_at', 'updated_at'],
            'feature_usage' => ['id', 'uuid', 'user_id', 'tenant_id', 'feature_id', 'period_starts_at', 'period_ends_at', 'quantity', 'created_at', 'updated_at'],
            'geographic_areas' => ['id', 'uuid', 'country_id', 'parent_id', 'type', 'parent_type', 'parent_key', 'code', 'name_fr', 'name_ar', 'is_active', 'reference_source', 'effective_at', 'reference_version', 'created_at', 'updated_at', 'deleted_at'],
            'activity_log' => ['id', 'uuid', 'tenant_id', 'operation_key', 'subject_id', 'causer_id', 'log_name', 'description', 'subject_type', 'event', 'causer_type', 'attribute_changes', 'properties', 'correlation_id', 'origin', 'created_at', 'updated_at'],
            'tenant_schema_deployments' => ['id', 'uuid', 'tenant_id', 'source_version', 'target_version', 'operation', 'status', 'attempt_number', 'operation_key', 'started_at', 'ended_at', 'error_code', 'sanitized_error', 'correlation_id', 'runtime_versions', 'created_at', 'updated_at'],
            'saas_invoices' => ['id', 'uuid', 'user_id', 'subscription_id', 'installment_id', 'billing_rule_id', 'original_invoice_id', 'sequence_id', 'document_media_id', 'number', 'operation_key', 'document_type', 'subscription_record_type', 'installment_record_type', 'billing_rule_record_type', 'original_invoice_document_type', 'sequence_record_type', 'billing_rule_snapshot', 'fiscal_year', 'sequence_number', 'net_amount', 'taxes', 'tax_amount', 'total_amount', 'currency', 'status', 'reason', 'period_starts_at', 'period_ends_at', 'due_at', 'saas_identity_snapshot', 'customer_identity_snapshot', 'issued_at', 'cancelled_at', 'cancellation_reason', 'correlation_id', 'created_at', 'updated_at'],
            'saas_invoice_lines' => ['id', 'uuid', 'document_id', 'user_id', 'original_invoice_id', 'original_invoice_line_id', 'operation_key', 'document_type', 'original_line_document_type', 'line_number', 'description', 'quantity', 'net_unit_price', 'net_discount', 'net_amount', 'taxes', 'tax_amount', 'total_amount', 'reason', 'correlation_id', 'created_at', 'updated_at'],
            'saas_billing_settings' => ['id', 'uuid', 'created_by_id', 'validated_by_id', 'operation_key', 'record_type', 'document_type', 'fiscal_year', 'prefix', 'next_number', 'sequence_slot', 'code', 'version', 'trigger_event', 'numbering_scope', 'parameters', 'policy_status', 'validation_reference', 'effective_at', 'ends_at', 'validated_at', 'correlation_id', 'created_at', 'updated_at'],
            'saas_document_deliveries' => ['id', 'uuid', 'user_id', 'document_id', 'created_by_id', 'proof_media_id', 'operation_key', 'document_type', 'channel', 'encrypted_recipient', 'delivery_status', 'attempts_count', 'delivery_attempts', 'next_attempt_at', 'sent_at', 'delivered_at', 'provider_reference', 'error_code', 'sending_started_at', 'correlation_id', 'created_at', 'updated_at'],
            'saas_transfers' => ['id', 'uuid', 'user_id', 'document_id', 'original_payment_id', 'credit_note_id', 'proof_media_id', 'source_proof_media_id', 'created_by_id', 'validated_by_id', 'performed_by_id', 'reversal_of_id', 'active_transaction_fingerprint', 'operation_key', 'record_type', 'document_type', 'original_payment_record_type', 'credit_note_document_type', 'transfer_method', 'transfer_status', 'refund_reason', 'amount', 'currency', 'reason', 'transfer_reference', 'financial_account_key', 'transaction_fingerprint', 'encrypted_transfer_details', 'occurred_at', 'sending_started_at', 'validated_at', 'error_code', 'correlation_id', 'created_at', 'updated_at'],
            'media' => ['id', 'uuid', 'created_by_id', 'storage_key', 'model_id', 'model_type', 'collection_name', 'disk', 'mime_type', 'original_name', 'size_bytes', 'width', 'height', 'duration_seconds', 'alt_text', 'visibility', 'position', 'is_primary', 'primary_slot', 'file_hash', 'created_at', 'updated_at', 'deleted_at'],
            'shipping_carriers' => ['id', 'uuid', 'code', 'name', 'adapter', 'default_api_url', 'is_active', 'reference_source', 'reference_version', 'synced_at', 'created_at', 'updated_at', 'deleted_at'],
            'carrier_geo_mappings' => ['id', 'uuid', 'carrier_id', 'geographic_area_id', 'zone_type', 'external_code', 'external_name', 'external_province_code', 'verification_source', 'verified_at', 'mapping_version', 'is_active', 'synced_at', 'created_at', 'updated_at'],
            'pickup_points' => ['id', 'uuid', 'carrier_id', 'province_id', 'municipality_id', 'province_type', 'municipality_type', 'external_code', 'name', 'address', 'phone', 'map_url', 'is_carrier_active', 'reference_source', 'reference_version', 'synced_at', 'created_at', 'updated_at', 'deleted_at'],
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
            ['users', 'ck_users_d9dae4bf96', 'legal_verification_status IS NULL OR legal_verification_status IN (1, 2, 3, 4, 5)'],
            ['users', 'ck_users_24b1d83b73', 'status IN (1, 2, 3, 4)'],
            ['users', 'ck_users_f07206f506', 'share_capital IS NULL OR share_capital >= 0'],
            ['users', 'ck_users_aab3127b68', 'legal_verification_status IS NULL OR legal_verification_status <> 2 OR (legal_verified_at IS NOT NULL AND legal_verified_by_id IS NOT NULL)'],
            ['tenants', 'ck_tenants_1945962f41', 'status IN (1, 2, 3, 4, 5, 6, 9)'],
            ['domains', 'ck_domains_74be1e9e32', 'type IN (1, 2)'],
            ['domains', 'ck_domains_d54844d1c3', 'verification_status IN (1, 2, 3, 4, 5)'],
            ['domains', 'ck_domains_12140937cf', 'certificate_status IS NULL OR certificate_status IN (1, 2, 3, 4)'],
            ['contact_verifications', 'ck_contact_verifications_40f36046fc', 'channel IN (1, 2, 3)'],
            ['contact_verifications', 'ck_contact_verifications_8a9891f71d', 'attempts_count >= 0'],
            ['features', 'ck_features_62a2d41fad', 'value_type IN (1, 2)'],
            ['features', 'ck_features_fc3fc0641a', 'quota_scope IN (1, 2)'],
            ['features', 'ck_features_8af247ae36', 'period IN (1, 2, 3, 4)'],
            ['admin_restrictions', 'ck_admin_restrictions_a3b7c3cb37', 'effect IN (1, 2)'],
            ['admin_restrictions', 'ck_admin_restrictions_24b1d83b73', 'status IN (1, 2, 3, 4)'],
            ['admin_restrictions', 'ck_admin_restrictions_8a5e522dfb', '(CASE WHEN target_tenant_id IS NULL THEN 0 ELSE 1 END + CASE WHEN target_user_id IS NULL THEN 0 ELSE 1 END + CASE WHEN target_role_id IS NULL THEN 0 ELSE 1 END) <= 1'],
            ['admin_restrictions', 'ck_admin_restrictions_10894000e8', 'ended_at IS NULL OR ended_at > started_at'],
            ['admin_restrictions', 'ck_admin_restrictions_d9949263f7', 'expires_at IS NULL OR expires_at > started_at'],
            ['subscriptions', 'ck_subscriptions_cbccd3efcf', 'record_type IN (1, 2)'],
            ['subscriptions', 'ck_subscriptions_dde99fadec', 'status IS NULL OR status IN (1, 2, 3, 4, 5)'],
            ['subscriptions', 'ck_subscriptions_515d3f3305', 'period IS NULL OR period IN (1, 2)'],
            ['subscriptions', 'ck_subscriptions_990cf763fa', 'installment_status IS NULL OR installment_status IN (1, 2, 3, 4)'],
            ['subscriptions', 'ck_subscriptions_3ef1a19ce0', 'period_ends_at IS NULL OR period_ends_at > period_starts_at'],
            ['subscriptions', 'ck_subscriptions_cf8f2c9eca', '(record_type = 1 AND parent_subscription_id IS NULL AND plan_id IS NOT NULL AND status IS NOT NULL AND period IS NOT NULL AND agreed_amount IS NOT NULL AND started_at IS NOT NULL AND auto_renew IS NOT NULL AND installment_number IS NULL AND installment_amount IS NULL AND due_at IS NULL AND installment_status IS NULL) OR (record_type = 2 AND parent_subscription_id IS NOT NULL AND installment_number IS NOT NULL AND installment_amount IS NOT NULL AND due_at IS NOT NULL AND installment_status IS NOT NULL AND period_ends_at IS NOT NULL AND tenant_id IS NULL AND plan_id IS NULL AND status IS NULL AND period IS NULL AND agreed_amount IS NULL AND started_at IS NULL AND trial_ends_at IS NULL AND ended_at IS NULL AND auto_renew IS NULL AND assigned_by_id IS NULL)'],
            ['subscriptions', 'ck_subscriptions_a056276a62', '(agreed_amount IS NULL OR agreed_amount >= 0) AND (installment_amount IS NULL OR installment_amount >= 0)'],
            ['geographic_areas', 'ck_geographic_areas_74be1e9e32', 'type IN (1, 2)'],
            ['geographic_areas', 'ck_geographic_areas_8d9fc87e7c', '(type = 1 AND parent_id IS NULL) OR (type = 2 AND parent_id IS NOT NULL)'],
            ['activity_log', 'ck_activity_log_762cba2a33', 'origin IN (1, 2, 3, 4)'],
            ['activity_log', 'ck_activity_log_6a73f15349', '(subject_type IS NULL AND subject_id IS NULL) OR (subject_type IS NOT NULL AND subject_id IS NOT NULL)'],
            ['activity_log', 'ck_activity_log_04eb33cd66', '(causer_type IS NULL AND causer_id IS NULL) OR (causer_type IS NOT NULL AND causer_id IS NOT NULL)'],
            ['tenant_schema_deployments', 'ck_tenant_schema_deployments_b18203e3b5', 'operation IN (1, 2)'],
            ['tenant_schema_deployments', 'ck_tenant_schema_deployments_757dbec8d7', 'status IN (1, 2, 3, 4, 5)'],
            ['tenant_schema_deployments', 'ck_tenant_schema_deployments_a6aa1e74fa', 'attempt_number > 0'],
            ['saas_invoices', 'ck_saas_invoices_6ed49a29e3', 'document_type IN (1, 2)'],
            ['saas_invoices', 'ck_saas_invoices_24b1d83b73', 'status IN (1, 2, 3, 4)'],
            ['saas_invoices', 'ck_saas_invoices_2bdb6c3ebc', 'net_amount >= 0 AND tax_amount >= 0 AND total_amount >= 0 AND net_amount + tax_amount = total_amount'],
            ['saas_invoices', 'ck_saas_invoices_8d7b5a3a38', 'period_ends_at > period_starts_at'],
            ['saas_invoices', 'ck_saas_invoices_bd2077b3ec', '(document_type = 1 AND original_invoice_id IS NULL AND due_at IS NOT NULL) OR (document_type = 2 AND original_invoice_id IS NOT NULL AND reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) > 0 AND due_at IS NULL)'],
            ['saas_invoices', 'ck_saas_invoices_204a0bea84', '(sequence_id IS NULL AND fiscal_year IS NULL AND sequence_number IS NULL AND number IS NULL) OR (sequence_id IS NOT NULL AND fiscal_year IS NOT NULL AND sequence_number IS NOT NULL AND number IS NOT NULL AND sequence_number > 0)'],
            ['saas_invoices', 'ck_saas_invoices_5730245384', 'status NOT IN (2,4) OR (sequence_id IS NOT NULL AND fiscal_year IS NOT NULL AND sequence_number IS NOT NULL AND number IS NOT NULL)'],
            ['saas_invoices', 'ck_saas_invoices_179a64b938', 'status <> 2 OR (document_media_id IS NOT NULL AND issued_at IS NOT NULL)'],
            ['saas_invoices', 'ck_saas_invoices_fc86a60810', 'status <> 3 OR (cancelled_at IS NOT NULL AND cancellation_reason IS NOT NULL AND CHAR_LENGTH(TRIM(cancellation_reason)) > 0)'],
            ['saas_invoices', 'ck_saas_invoices_97d0e82a59', '(document_type = 1 AND operation_key LIKE \'saas:invoice:%\') OR (document_type = 2 AND operation_key LIKE \'saas:credit:%\')'],
            ['saas_invoice_lines', 'ck_saas_invoice_lines_6ed49a29e3', 'document_type IN (1, 2)'],
            ['saas_invoice_lines', 'ck_saas_invoice_lines_2bdb6c3ebc', 'net_amount >= 0 AND tax_amount >= 0 AND total_amount >= 0 AND net_amount + tax_amount = total_amount'],
            ['saas_invoice_lines', 'ck_saas_invoice_lines_a9a672000a', 'line_number > 0 AND quantity > 0'],
            ['saas_invoice_lines', 'ck_saas_invoice_lines_a260d3a13e', '(document_type = 1 AND original_invoice_id IS NULL AND original_invoice_line_id IS NULL AND reason IS NULL AND net_unit_price IS NOT NULL AND net_unit_price >= 0 AND net_discount IS NOT NULL AND net_discount >= 0) OR (document_type = 2 AND original_invoice_id IS NOT NULL AND original_invoice_line_id IS NOT NULL AND reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) > 0 AND net_unit_price IS NULL AND net_discount IS NULL)'],
            ['saas_invoice_lines', 'ck_saas_invoice_lines_c2cc9d4cba', '(document_type = 1 AND operation_key LIKE \'saas:line:%\') OR (document_type = 2 AND operation_key LIKE \'saas:line:%\')'],
            ['saas_billing_settings', 'ck_saas_billing_settings_cbccd3efcf', 'record_type IN (1, 2)'],
            ['saas_billing_settings', 'ck_saas_billing_settings_25f4ff1481', 'document_type IS NULL OR document_type IN (1, 2)'],
            ['saas_billing_settings', 'ck_saas_billing_settings_6dac4dd1df', 'policy_status IS NULL OR policy_status IN (1, 2, 3, 4)'],
            ['saas_billing_settings', 'ck_saas_billing_settings_47d1601012', '(record_type = 1 AND document_type IS NOT NULL AND fiscal_year IS NOT NULL AND prefix IS NOT NULL AND next_number IS NOT NULL AND next_number > 0 AND code IS NULL AND version IS NULL AND trigger_event IS NULL AND numbering_scope IS NULL AND parameters IS NULL AND policy_status IS NULL AND validated_by_id IS NULL AND validated_at IS NULL AND validation_reference IS NULL AND effective_at IS NULL AND ends_at IS NULL AND created_by_id IS NULL AND correlation_id IS NULL) OR (record_type = 2 AND document_type IS NULL AND fiscal_year IS NULL AND prefix IS NULL AND next_number IS NULL AND code IS NOT NULL AND version IS NOT NULL AND trigger_event IS NOT NULL AND numbering_scope IS NOT NULL AND parameters IS NOT NULL AND policy_status IS NOT NULL AND version > 0 AND numbering_scope = \'saas_issuer\')'],
            ['saas_billing_settings', 'ck_saas_billing_settings_f9573e3e8d', 'policy_status IS NULL OR policy_status NOT IN (2,3) OR (validated_by_id IS NOT NULL AND validated_at IS NOT NULL AND validation_reference IS NOT NULL)'],
            ['saas_billing_settings', 'ck_saas_billing_settings_7b93dc0447', 'policy_status IS NULL OR policy_status <> 3 OR effective_at IS NOT NULL'],
            ['saas_billing_settings', 'ck_saas_billing_settings_4b77689abb', 'ends_at IS NULL OR (effective_at IS NOT NULL AND ends_at > effective_at)'],
            ['saas_billing_settings', 'ck_saas_billing_settings_0e843e97ad', 'record_type <> 2 OR correlation_id IS NOT NULL'],
            ['saas_billing_settings', 'ck_saas_billing_settings_3c24af7185', '(record_type = 1 AND operation_key LIKE \'saas:sequence:%\') OR (record_type = 2 AND operation_key LIKE \'saas:rule:%\')'],
            ['saas_document_deliveries', 'ck_saas_document_deliveries_6ed49a29e3', 'document_type IN (1, 2)'],
            ['saas_document_deliveries', 'ck_saas_document_deliveries_394b9ad659', 'channel IN (1, 2, 3, 4)'],
            ['saas_document_deliveries', 'ck_saas_document_deliveries_8ec3b29853', 'delivery_status IN (1, 2, 3, 4, 5, 6, 7, 8)'],
            ['saas_document_deliveries', 'ck_saas_document_deliveries_6456ca7965', 'attempts_count >= 0 AND CHAR_LENGTH(TRIM(encrypted_recipient)) > 0'],
            ['saas_document_deliveries', 'ck_saas_document_deliveries_b196e4a1f5', 'operation_key LIKE \'saas:delivery:%\''],
            ['saas_transfers', 'ck_saas_transfers_cbccd3efcf', 'record_type IN (1, 2)'],
            ['saas_transfers', 'ck_saas_transfers_829a434184', 'transfer_method IN (1, 2, 3, 4)'],
            ['saas_transfers', 'ck_saas_transfers_6a141bd992', 'transfer_status IN (1, 2, 3, 4, 5, 6, 7)'],
            ['saas_transfers', 'ck_saas_transfers_5383ab89bb', 'refund_reason IS NULL OR refund_reason IN (1, 2, 3, 4)'],
            ['saas_transfers', 'ck_saas_transfers_2cfb962f81', '(reversal_of_id IS NULL AND amount > 0) OR (reversal_of_id IS NOT NULL AND amount < 0)'],
            ['saas_transfers', 'ck_saas_transfers_e4b81e25c5', '(record_type = 1 AND original_payment_id IS NULL AND credit_note_id IS NULL AND refund_reason IS NULL AND performed_by_id IS NULL) OR (record_type = 2 AND original_payment_id IS NOT NULL AND refund_reason IS NOT NULL AND created_by_id IS NOT NULL AND reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) > 0)'],
            ['saas_transfers', 'ck_saas_transfers_32066a0cb1', 'record_type <> 2 OR refund_reason <> 1 OR credit_note_id IS NOT NULL'],
            ['saas_transfers', 'ck_saas_transfers_b78f7cba93', 'transfer_status <> 3 OR (proof_media_id IS NOT NULL AND transfer_reference IS NOT NULL AND financial_account_key IS NOT NULL AND transaction_fingerprint IS NOT NULL AND occurred_at IS NOT NULL AND validated_by_id IS NOT NULL AND validated_at IS NOT NULL)'],
            ['saas_transfers', 'ck_saas_transfers_7906062765', 'record_type <> 2 OR transfer_status <> 3 OR (performed_by_id IS NOT NULL AND sending_started_at IS NOT NULL)'],
            ['saas_transfers', 'ck_saas_transfers_383a47c1c9', '(record_type = 1 AND operation_key LIKE \'saas:payment:%\') OR (record_type = 2 AND operation_key LIKE \'saas:refund:%\')'],
            ['media', 'ck_media_b762576c60', 'visibility IN (1, 2)'],
            ['media', 'ck_media_9039b28f88', 'position >= 0'],
            ['carrier_geo_mappings', 'ck_carrier_geo_mappings_3f400ee71f', 'zone_type IN (1, 2)'],
            ['permissions', 'ck_permissions_518cf8da0c', 'guard_name = \'central\''],
            ['roles', 'ck_roles_518cf8da0c', 'guard_name = \'central\''],
            ['roles', 'ck_roles_85d78dec00', 'CHAR_LENGTH(TRIM(name)) > 0'],
            ['role_has_permissions', 'ck_role_has_permissions_61a9c661de', 'duration_days BETWEEN 1 AND 9999'],
            ['model_has_roles', 'ck_model_has_roles_b9b54c2fb8', 'model_type = \'central_user\''],
            ['model_has_permissions', 'ck_model_has_permissions_b9b54c2fb8', 'model_type = \'central_user\''],
            ['model_has_permissions', 'ck_model_has_permissions_a55ae2c2be', 'expires_at > assigned_at AND expires_at <= DATE_ADD(assigned_at, INTERVAL 9999 DAY)'],
            ['plans', 'ck_plans_3346b4dab0', 'version > 0 AND monthly_price >= 0 AND annual_price >= 0'],
            ['plan_features', 'ck_plan_features_5c14075980', '`limit` IS NULL OR `limit` >= 0'],
            ['feature_usage', 'ck_feature_usage_26fa38578d', 'quantity >= 0 AND (period_ends_at IS NULL OR period_ends_at > period_starts_at)'],
        ];
    }
};
