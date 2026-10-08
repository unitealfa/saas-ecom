<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->checks() as [$table, $name, $expression]) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
        }
    }

    public function down(): void
    {
        foreach ($this->checks() as [$table, $name]) {
            DB::statement("ALTER TABLE `{$table}` DROP CHECK `{$name}`");
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
