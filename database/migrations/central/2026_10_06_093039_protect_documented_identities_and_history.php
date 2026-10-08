<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->protections() as [$table, $event, $name, $condition, $message]) {
            $timing = str_starts_with($event, 'AFTER ') ? 'AFTER' : 'BEFORE';
            $event = str_replace('AFTER ', '', $event);
            $condition = preg_replace_callback('/OLD\.(\w+) IS NOT NEW\.(\w+)/', fn (array $match): string => "NOT (OLD.`{$match[1]}` <=> NEW.`{$match[2]}`)", $condition);
            DB::statement("CREATE TRIGGER {$name} {$timing} {$event} ON `{$table}` FOR EACH ROW BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END");
        }
    }

    public function down(): void
    {
        foreach ($this->protections() as [, , $name]) {
            DB::statement("DROP TRIGGER IF EXISTS `{$name}`");
        }
    }

    /**
     * @return list<array{string, string, string, string, string}>
     */
    private function protections(): array
    {
        return [
            ['countries', 'UPDATE', 'guard_countries_63991c7934', '(1) AND (OLD.id IS NOT NEW.id)', 'Immutable documented identity'],
            ['users', 'UPDATE', 'guard_users_63991c7934', '(1) AND (OLD.id IS NOT NEW.id)', 'Immutable documented identity'],
            ['tenants', 'UPDATE', 'guard_tenants_63991c7934', '(1) AND (OLD.id IS NOT NEW.id)', 'Immutable documented identity'],
            ['domains', 'UPDATE', 'guard_domains_63991c7934', '(1) AND (OLD.id IS NOT NEW.id)', 'Immutable documented identity'],
            ['contact_verifications', 'UPDATE', 'guard_contact_verifications_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['features', 'UPDATE', 'guard_features_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['permissions', 'UPDATE', 'guard_permissions_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['roles', 'UPDATE', 'guard_roles_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['admin_restrictions', 'UPDATE', 'guard_admin_restrictions_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['plans', 'UPDATE', 'guard_plans_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['plan_features', 'UPDATE', 'guard_plan_features_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['subscriptions', 'UPDATE', 'guard_subscriptions_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['feature_usage', 'UPDATE', 'guard_feature_usage_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['geographic_areas', 'UPDATE', 'guard_geographic_areas_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['geographic_areas', 'INSERT', 'guard_geographic_areas_27f20b2485', 'NEW.parent_id = NEW.id', 'Self reference is forbidden'],
            ['geographic_areas', 'AFTER INSERT', 'guard_geographic_areas_e50e21653d', 'NEW.parent_id = NEW.id', 'Self reference is forbidden'],
            ['geographic_areas', 'UPDATE', 'guard_geographic_areas_0842ef0d6b', 'NEW.parent_id = NEW.id', 'Self reference is forbidden'],
            ['activity_log', 'UPDATE', 'guard_activity_log_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['tenant_schema_deployments', 'UPDATE', 'guard_tenant_schema_deployments_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['saas_invoices', 'UPDATE', 'guard_saas_invoices_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['saas_invoices', 'INSERT', 'guard_saas_invoices_d4f5415ab8', 'NEW.original_invoice_id = NEW.id', 'Self reference is forbidden'],
            ['saas_invoices', 'AFTER INSERT', 'guard_saas_invoices_48b4e0364b', 'NEW.original_invoice_id = NEW.id', 'Self reference is forbidden'],
            ['saas_invoices', 'UPDATE', 'guard_saas_invoices_3bf0dd2842', 'NEW.original_invoice_id = NEW.id', 'Self reference is forbidden'],
            ['saas_invoice_lines', 'UPDATE', 'guard_saas_invoice_lines_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['saas_invoice_lines', 'INSERT', 'guard_saas_invoice_lines_d4f5415ab8', 'NEW.original_invoice_id = NEW.id', 'Self reference is forbidden'],
            ['saas_invoice_lines', 'AFTER INSERT', 'guard_saas_invoice_lines_48b4e0364b', 'NEW.original_invoice_id = NEW.id', 'Self reference is forbidden'],
            ['saas_invoice_lines', 'UPDATE', 'guard_saas_invoice_lines_3bf0dd2842', 'NEW.original_invoice_id = NEW.id', 'Self reference is forbidden'],
            ['saas_billing_settings', 'UPDATE', 'guard_saas_billing_settings_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['saas_document_deliveries', 'UPDATE', 'guard_saas_document_deliveries_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['saas_transfers', 'UPDATE', 'guard_saas_transfers_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['saas_transfers', 'INSERT', 'guard_saas_transfers_0d5c98de48', 'NEW.reversal_of_id = NEW.id', 'Self reference is forbidden'],
            ['saas_transfers', 'AFTER INSERT', 'guard_saas_transfers_554d12ae48', 'NEW.reversal_of_id = NEW.id', 'Self reference is forbidden'],
            ['saas_transfers', 'UPDATE', 'guard_saas_transfers_3747d9d631', 'NEW.reversal_of_id = NEW.id', 'Self reference is forbidden'],
            ['saas_transfers', 'INSERT', 'guard_saas_transfers_a46707ba5d', 'NEW.original_payment_id = NEW.id', 'Self reference is forbidden'],
            ['saas_transfers', 'AFTER INSERT', 'guard_saas_transfers_2c51be90d5', 'NEW.original_payment_id = NEW.id', 'Self reference is forbidden'],
            ['saas_transfers', 'UPDATE', 'guard_saas_transfers_42e05f4408', 'NEW.original_payment_id = NEW.id', 'Self reference is forbidden'],
            ['media', 'UPDATE', 'guard_media_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['shipping_carriers', 'UPDATE', 'guard_shipping_carriers_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['carrier_geo_mappings', 'UPDATE', 'guard_carrier_geo_mappings_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['pickup_points', 'UPDATE', 'guard_pickup_points_e2fc712050', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid)', 'Immutable documented identity'],
            ['roles', 'UPDATE', 'guard_roles_7ca3d583dc', '(1) AND (OLD.guard_name IS NOT NEW.guard_name OR OLD.is_super_admin IS NOT NEW.is_super_admin)', 'Immutable documented identity'],
            ['permissions', 'UPDATE', 'guard_permissions_1d4f22737e', '(1) AND (OLD.guard_name IS NOT NEW.guard_name)', 'Immutable documented identity'],
            ['roles', 'DELETE', 'guard_roles_da840ce7c0', 'OLD.is_protected = TRUE OR OLD.is_system = TRUE OR OLD.is_super_admin = TRUE', 'Protected role cannot be deleted'],
            ['activity_log', 'UPDATE', 'guard_activity_log_898b2c4d7c', '(1) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid OR OLD.tenant_id IS NOT NEW.tenant_id OR OLD.operation_key IS NOT NEW.operation_key OR OLD.subject_id IS NOT NEW.subject_id OR OLD.causer_id IS NOT NEW.causer_id OR OLD.log_name IS NOT NEW.log_name OR OLD.description IS NOT NEW.description OR OLD.subject_type IS NOT NEW.subject_type OR OLD.event IS NOT NEW.event OR OLD.causer_type IS NOT NEW.causer_type OR OLD.attribute_changes IS NOT NEW.attribute_changes OR OLD.properties IS NOT NEW.properties OR OLD.correlation_id IS NOT NEW.correlation_id OR OLD.origin IS NOT NEW.origin OR OLD.created_at IS NOT NEW.created_at)', 'Immutable documented history'],
            ['activity_log', 'DELETE', 'guard_activity_log_9ea3c78d14', '1', 'Documented history cannot be deleted'],
            ['subscriptions', 'UPDATE', 'guard_subscriptions_96518beadc', '(1) AND (OLD.record_type IS NOT NEW.record_type OR OLD.user_id IS NOT NEW.user_id OR OLD.parent_subscription_id IS NOT NEW.parent_subscription_id)', 'Immutable documented identity'],
            ['geographic_areas', 'UPDATE', 'guard_geographic_areas_3d94ccb6b7', '(1) AND (OLD.type IS NOT NEW.type OR OLD.country_id IS NOT NEW.country_id OR OLD.parent_id IS NOT NEW.parent_id)', 'Immutable documented identity'],
            ['users', 'DELETE', 'guard_users_a45f864f88', 'EXISTS (SELECT 1 FROM model_has_roles m JOIN roles r ON r.id = m.role_id WHERE m.model_id = OLD.id AND m.model_type = \'central_user\' AND r.is_super_admin = TRUE)', 'Protected root cannot be deleted'],
            ['users', 'UPDATE', 'guard_users_dc309c4701', '(EXISTS (SELECT 1 FROM model_has_roles m JOIN roles r ON r.id = m.role_id WHERE m.model_id = OLD.id AND m.model_type = \'central_user\' AND r.is_super_admin = TRUE)) AND (NEW.status <> 1 OR NEW.deleted_at IS NOT NULL)', 'An assigned root must remain active'],
            ['saas_invoices', 'UPDATE', 'guard_saas_invoices_d1633dd0f6', '(1) AND (OLD.document_type IS NOT NEW.document_type OR OLD.user_id IS NOT NEW.user_id OR OLD.subscription_id IS NOT NEW.subscription_id OR OLD.installment_id IS NOT NEW.installment_id OR OLD.original_invoice_id IS NOT NEW.original_invoice_id OR OLD.operation_key IS NOT NEW.operation_key)', 'Immutable documented identity'],
            ['saas_invoice_lines', 'UPDATE', 'guard_saas_invoice_lines_67078991af', '(1) AND (OLD.document_type IS NOT NEW.document_type OR OLD.document_id IS NOT NEW.document_id OR OLD.user_id IS NOT NEW.user_id OR OLD.original_invoice_id IS NOT NEW.original_invoice_id OR OLD.original_invoice_line_id IS NOT NEW.original_invoice_line_id OR OLD.operation_key IS NOT NEW.operation_key)', 'Immutable documented identity'],
            ['saas_transfers', 'UPDATE', 'guard_saas_transfers_902ee6bb83', '(1) AND (OLD.record_type IS NOT NEW.record_type OR OLD.user_id IS NOT NEW.user_id OR OLD.document_id IS NOT NEW.document_id OR OLD.original_payment_id IS NOT NEW.original_payment_id OR OLD.credit_note_id IS NOT NEW.credit_note_id OR OLD.reversal_of_id IS NOT NEW.reversal_of_id OR OLD.operation_key IS NOT NEW.operation_key)', 'Immutable documented identity'],
            ['saas_transfers', 'UPDATE', 'guard_saas_transfers_8e4889a3e1', '(OLD.transfer_status IN (3,7)) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid OR OLD.user_id IS NOT NEW.user_id OR OLD.document_id IS NOT NEW.document_id OR OLD.original_payment_id IS NOT NEW.original_payment_id OR OLD.credit_note_id IS NOT NEW.credit_note_id OR OLD.proof_media_id IS NOT NEW.proof_media_id OR OLD.source_proof_media_id IS NOT NEW.source_proof_media_id OR OLD.created_by_id IS NOT NEW.created_by_id OR OLD.validated_by_id IS NOT NEW.validated_by_id OR OLD.performed_by_id IS NOT NEW.performed_by_id OR OLD.reversal_of_id IS NOT NEW.reversal_of_id OR OLD.operation_key IS NOT NEW.operation_key OR OLD.record_type IS NOT NEW.record_type OR OLD.transfer_method IS NOT NEW.transfer_method OR OLD.refund_reason IS NOT NEW.refund_reason OR OLD.amount IS NOT NEW.amount OR OLD.currency IS NOT NEW.currency OR OLD.reason IS NOT NEW.reason OR OLD.transfer_reference IS NOT NEW.transfer_reference OR OLD.financial_account_key IS NOT NEW.financial_account_key OR OLD.transaction_fingerprint IS NOT NEW.transaction_fingerprint OR OLD.encrypted_transfer_details IS NOT NEW.encrypted_transfer_details OR OLD.occurred_at IS NOT NEW.occurred_at OR OLD.sending_started_at IS NOT NEW.sending_started_at OR OLD.validated_at IS NOT NEW.validated_at OR OLD.error_code IS NOT NEW.error_code OR OLD.correlation_id IS NOT NEW.correlation_id OR OLD.created_at IS NOT NEW.created_at)', 'Immutable documented history'],
            ['saas_transfers', 'DELETE', 'guard_saas_transfers_67192d9aa2', 'OLD.transfer_status IN (3,7)', 'Documented history cannot be deleted'],
            ['saas_invoices', 'UPDATE', 'guard_saas_invoices_2a8eda5324', '(OLD.status = 2) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid OR OLD.user_id IS NOT NEW.user_id OR OLD.subscription_id IS NOT NEW.subscription_id OR OLD.installment_id IS NOT NEW.installment_id OR OLD.billing_rule_id IS NOT NEW.billing_rule_id OR OLD.original_invoice_id IS NOT NEW.original_invoice_id OR OLD.sequence_id IS NOT NEW.sequence_id OR OLD.document_media_id IS NOT NEW.document_media_id OR OLD.number IS NOT NEW.number OR OLD.operation_key IS NOT NEW.operation_key OR OLD.document_type IS NOT NEW.document_type OR OLD.billing_rule_snapshot IS NOT NEW.billing_rule_snapshot OR OLD.fiscal_year IS NOT NEW.fiscal_year OR OLD.sequence_number IS NOT NEW.sequence_number OR OLD.net_amount IS NOT NEW.net_amount OR OLD.taxes IS NOT NEW.taxes OR OLD.tax_amount IS NOT NEW.tax_amount OR OLD.total_amount IS NOT NEW.total_amount OR OLD.currency IS NOT NEW.currency OR OLD.status IS NOT NEW.status OR OLD.reason IS NOT NEW.reason OR OLD.period_starts_at IS NOT NEW.period_starts_at OR OLD.period_ends_at IS NOT NEW.period_ends_at OR OLD.due_at IS NOT NEW.due_at OR OLD.saas_identity_snapshot IS NOT NEW.saas_identity_snapshot OR OLD.customer_identity_snapshot IS NOT NEW.customer_identity_snapshot OR OLD.issued_at IS NOT NEW.issued_at OR OLD.cancelled_at IS NOT NEW.cancelled_at OR OLD.cancellation_reason IS NOT NEW.cancellation_reason OR OLD.correlation_id IS NOT NEW.correlation_id OR OLD.created_at IS NOT NEW.created_at)', 'Immutable documented history'],
            ['saas_invoices', 'DELETE', 'guard_saas_invoices_dc13c0d958', 'OLD.status = 2', 'Documented history cannot be deleted'],
            ['saas_invoices', 'UPDATE', 'guard_saas_invoices_150cd99eaf', '(OLD.status = 4) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid OR OLD.user_id IS NOT NEW.user_id OR OLD.subscription_id IS NOT NEW.subscription_id OR OLD.installment_id IS NOT NEW.installment_id OR OLD.billing_rule_id IS NOT NEW.billing_rule_id OR OLD.original_invoice_id IS NOT NEW.original_invoice_id OR OLD.sequence_id IS NOT NEW.sequence_id OR OLD.number IS NOT NEW.number OR OLD.operation_key IS NOT NEW.operation_key OR OLD.document_type IS NOT NEW.document_type OR OLD.billing_rule_snapshot IS NOT NEW.billing_rule_snapshot OR OLD.fiscal_year IS NOT NEW.fiscal_year OR OLD.sequence_number IS NOT NEW.sequence_number OR OLD.net_amount IS NOT NEW.net_amount OR OLD.taxes IS NOT NEW.taxes OR OLD.tax_amount IS NOT NEW.tax_amount OR OLD.total_amount IS NOT NEW.total_amount OR OLD.currency IS NOT NEW.currency OR OLD.reason IS NOT NEW.reason OR OLD.period_starts_at IS NOT NEW.period_starts_at OR OLD.period_ends_at IS NOT NEW.period_ends_at OR OLD.due_at IS NOT NEW.due_at OR OLD.saas_identity_snapshot IS NOT NEW.saas_identity_snapshot OR OLD.customer_identity_snapshot IS NOT NEW.customer_identity_snapshot OR OLD.correlation_id IS NOT NEW.correlation_id OR OLD.created_at IS NOT NEW.created_at)', 'Immutable documented identity'],
            ['saas_invoice_lines', 'INSERT', 'guard_saas_invoice_lines_ddbb36112d', 'EXISTS (SELECT 1 FROM saas_invoices d WHERE d.id = NEW.document_id AND d.status IN (2,4))', 'Reserved document lines are immutable'],
            ['saas_invoice_lines', 'UPDATE', 'guard_saas_invoice_lines_1f4dd7328b', 'EXISTS (SELECT 1 FROM saas_invoices d WHERE d.id = OLD.document_id AND d.status IN (2,4)) OR EXISTS (SELECT 1 FROM saas_invoices d WHERE d.id = NEW.document_id AND d.status IN (2,4))', 'Reserved document lines are immutable'],
            ['saas_invoice_lines', 'DELETE', 'guard_saas_invoice_lines_1d22ecc63c', 'EXISTS (SELECT 1 FROM saas_invoices d WHERE d.id = OLD.document_id AND d.status IN (2,4))', 'Reserved document lines are immutable'],
            ['plans', 'UPDATE', 'guard_plans_8b17fdc985', '(EXISTS (SELECT 1 FROM subscriptions s WHERE s.plan_id = OLD.id)) AND (OLD.id IS NOT NEW.id OR OLD.uuid IS NOT NEW.uuid OR OLD.code IS NOT NEW.code OR OLD.version IS NOT NEW.version OR OLD.name IS NOT NEW.name OR OLD.description IS NOT NEW.description OR OLD.monthly_price IS NOT NEW.monthly_price OR OLD.annual_price IS NOT NEW.annual_price OR OLD.created_at IS NOT NEW.created_at OR OLD.deleted_at IS NOT NEW.deleted_at)', 'Immutable documented history'],
            ['plans', 'DELETE', 'guard_plans_fa3b54de16', 'EXISTS (SELECT 1 FROM subscriptions s WHERE s.plan_id = OLD.id)', 'Documented history cannot be deleted'],
            ['plan_features', 'INSERT', 'guard_plan_features_0447230943', 'EXISTS (SELECT 1 FROM subscriptions s WHERE s.plan_id = NEW.plan_id)', 'Used plan composition is immutable'],
            ['plan_features', 'UPDATE', 'guard_plan_features_614826f7e4', 'EXISTS (SELECT 1 FROM subscriptions s WHERE s.plan_id = OLD.plan_id) OR EXISTS (SELECT 1 FROM subscriptions s WHERE s.plan_id = NEW.plan_id)', 'Used plan composition is immutable'],
            ['plan_features', 'DELETE', 'guard_plan_features_f91dc7d114', 'EXISTS (SELECT 1 FROM subscriptions s WHERE s.plan_id = OLD.plan_id)', 'Used plan composition is immutable'],
            ['saas_billing_settings', 'UPDATE', 'guard_saas_billing_settings_959afe866a', '(1) AND (OLD.record_type IS NOT NEW.record_type)', 'Immutable documented identity'],
            ['saas_billing_settings', 'UPDATE', 'guard_saas_billing_settings_e5a581f3d0', '(OLD.record_type = 1 AND EXISTS (SELECT 1 FROM saas_invoices d WHERE d.sequence_id = OLD.id AND d.sequence_number IS NOT NULL)) AND (OLD.document_type IS NOT NEW.document_type OR OLD.fiscal_year IS NOT NEW.fiscal_year OR OLD.prefix IS NOT NEW.prefix)', 'Immutable documented identity'],
            ['saas_billing_settings', 'UPDATE', 'guard_saas_billing_settings_4520501bc3', 'OLD.record_type = 1 AND NEW.next_number < OLD.next_number', 'Document sequence cannot go backwards'],
            ['saas_billing_settings', 'UPDATE', 'guard_saas_billing_settings_c90ff136da', '(OLD.record_type = 2 AND OLD.policy_status IN (2,3,4)) AND (OLD.code IS NOT NEW.code OR OLD.version IS NOT NEW.version OR OLD.trigger_event IS NOT NEW.trigger_event OR OLD.numbering_scope IS NOT NEW.numbering_scope OR OLD.parameters IS NOT NEW.parameters)', 'Validated billing rule is immutable'],
            ['saas_billing_settings', 'DELETE', 'guard_saas_billing_settings_7d83926f8b', '(OLD.record_type = 1 AND EXISTS (SELECT 1 FROM saas_invoices d WHERE d.sequence_id = OLD.id AND d.sequence_number IS NOT NULL)) OR (OLD.record_type = 2 AND OLD.policy_status IN (2,3,4))', 'Used billing settings cannot be deleted'],
            ['saas_transfers', 'INSERT', 'guard_saas_transfers_7196b2a9de', 'NEW.reversal_of_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM saas_transfers o WHERE o.id = NEW.reversal_of_id AND o.reversal_of_id IS NULL AND NEW.amount = -o.amount)', 'Reversal must exactly invert an ordinary entry'],
            ['saas_transfers', 'UPDATE', 'guard_saas_transfers_4bde069141', 'NEW.reversal_of_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM saas_transfers o WHERE o.id = NEW.reversal_of_id AND o.reversal_of_id IS NULL AND NEW.amount = -o.amount)', 'Reversal must exactly invert an ordinary entry'],
        ];
    }
};
