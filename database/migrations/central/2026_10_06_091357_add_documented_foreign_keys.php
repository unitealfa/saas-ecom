<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::table('admin_restrictions', function (Blueprint $table): void {
            $table->foreign(['admin_id'], 'fk_admin_restrictions_f159b8e5cb')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['permission_id'], 'fk_admin_restrictions_50d0bf2736')->references(['id'])->on('permissions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['target_tenant_id'], 'fk_admin_restrictions_843f38c8c7')->references(['id'])->on('tenants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['target_user_id'], 'fk_admin_restrictions_a28a1cc1dd')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['target_role_id'], 'fk_admin_restrictions_d88b7f676a')->references(['id'])->on('roles')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['created_by_id'], 'fk_admin_restrictions_6fb667974b')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('plan_features', function (Blueprint $table): void {
            $table->foreign(['plan_id'], 'fk_plan_features_a427196bc8')->references(['id'])->on('plans')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['feature_id'], 'fk_plan_features_3de8f04067')->references(['id'])->on('features')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->foreign(['user_id'], 'fk_subscriptions_f89d6b6960')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['parent_subscription_id'], 'fk_subscriptions_acf499a627')->references(['id'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['tenant_id'], 'fk_subscriptions_759b6ffea8')->references(['id'])->on('tenants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['plan_id'], 'fk_subscriptions_a427196bc8')->references(['id'])->on('plans')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['assigned_by_id'], 'fk_subscriptions_bc15dd68ce')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['tenant_id', 'user_id'], 'fk_subscriptions_1bd81732ff')->references(['id', 'user_id'])->on('tenants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['parent_subscription_id', 'user_id', 'parent_record_type'], 'fk_subscriptions_e56456f06c')->references(['id', 'user_id', 'record_type'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('feature_usage', function (Blueprint $table): void {
            $table->foreign(['user_id'], 'fk_feature_usage_f89d6b6960')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['tenant_id'], 'fk_feature_usage_759b6ffea8')->references(['id'])->on('tenants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['feature_id'], 'fk_feature_usage_3de8f04067')->references(['id'])->on('features')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['tenant_id', 'user_id'], 'fk_feature_usage_1bd81732ff')->references(['id', 'user_id'])->on('tenants')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('geographic_areas', function (Blueprint $table): void {
            $table->foreign(['country_id'], 'fk_geographic_areas_1bd5d05f10')->references(['id'])->on('countries')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['parent_id'], 'fk_geographic_areas_7b54484fae')->references(['id'])->on('geographic_areas')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['parent_id', 'country_id', 'parent_type'], 'fk_geographic_areas_8a88bda689')->references(['id', 'country_id', 'type'])->on('geographic_areas')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'fk_activity_log_759b6ffea8')->references(['id'])->on('tenants')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('tenant_schema_deployments', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'fk_tenant_schema_deployments_759b6ffea8')->references(['id'])->on('tenants')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->foreign(['user_id'], 'fk_saas_invoices_f89d6b6960')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['subscription_id'], 'fk_saas_invoices_56c8a8bf72')->references(['id'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['installment_id'], 'fk_saas_invoices_b0a744b4f2')->references(['id'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['billing_rule_id'], 'fk_saas_invoices_7de4923723')->references(['id'])->on('saas_billing_settings')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_invoice_id'], 'fk_saas_invoices_ec080ce729')->references(['id'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sequence_id'], 'fk_saas_invoices_ac063d431a')->references(['id'])->on('saas_billing_settings')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['document_media_id'], 'fk_saas_invoices_1707541b1b')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['subscription_id', 'user_id', 'subscription_record_type'], 'fk_saas_invoices_fcc56c4a07')->references(['id', 'user_id', 'record_type'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['installment_id', 'subscription_id', 'user_id', 'installment_record_type'], 'fk_saas_invoices_b991245fce')->references(['id', 'parent_subscription_id', 'user_id', 'record_type'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['billing_rule_id', 'billing_rule_record_type'], 'fk_saas_invoices_1bae90140d')->references(['id', 'record_type'])->on('saas_billing_settings')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sequence_id', 'document_type', 'fiscal_year', 'sequence_record_type'], 'fk_saas_invoices_b70ad3b9df')->references(['id', 'document_type', 'fiscal_year', 'record_type'])->on('saas_billing_settings')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_invoice_id', 'user_id', 'original_invoice_document_type'], 'fk_saas_invoices_42bbb6432d')->references(['id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_invoice_id', 'installment_id', 'subscription_id', 'user_id', 'original_invoice_document_type'], 'fk_saas_invoices_2d79d283e4')->references(['id', 'installment_id', 'subscription_id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('saas_invoice_lines', function (Blueprint $table): void {
            $table->foreign(['document_id'], 'fk_saas_invoice_lines_2a8e659395')->references(['id'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['user_id'], 'fk_saas_invoice_lines_f89d6b6960')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_invoice_id'], 'fk_saas_invoice_lines_ec080ce729')->references(['id'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_invoice_line_id'], 'fk_saas_invoice_lines_7f3244d890')->references(['id'])->on('saas_invoice_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['document_id', 'user_id', 'document_type'], 'fk_saas_invoice_lines_8293563717')->references(['id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['document_id', 'original_invoice_id', 'user_id', 'document_type'], 'fk_saas_invoice_lines_cdd1b4ed69')->references(['id', 'original_invoice_id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_invoice_line_id', 'original_invoice_id', 'user_id', 'original_line_document_type'], 'fk_saas_invoice_lines_2dfa389c8b')->references(['id', 'document_id', 'user_id', 'document_type'])->on('saas_invoice_lines')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('saas_billing_settings', function (Blueprint $table): void {
            $table->foreign(['created_by_id'], 'fk_saas_billing_settings_6fb667974b')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['validated_by_id'], 'fk_saas_billing_settings_6a16de7f77')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('saas_document_deliveries', function (Blueprint $table): void {
            $table->foreign(['user_id'], 'fk_saas_document_deliveries_f89d6b6960')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['document_id'], 'fk_saas_document_deliveries_2a8e659395')->references(['id'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['created_by_id'], 'fk_saas_document_deliveries_6fb667974b')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['proof_media_id'], 'fk_saas_document_deliveries_6aed88530f')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['document_id', 'user_id', 'document_type'], 'fk_saas_document_deliveries_8293563717')->references(['id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->foreign(['user_id'], 'fk_saas_transfers_f89d6b6960')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['document_id'], 'fk_saas_transfers_2a8e659395')->references(['id'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_payment_id'], 'fk_saas_transfers_f1937ef24c')->references(['id'])->on('saas_transfers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['credit_note_id'], 'fk_saas_transfers_d61185a1fb')->references(['id'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['proof_media_id'], 'fk_saas_transfers_6aed88530f')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['source_proof_media_id'], 'fk_saas_transfers_2ac3d6fcc8')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['created_by_id'], 'fk_saas_transfers_6fb667974b')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['validated_by_id'], 'fk_saas_transfers_6a16de7f77')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['performed_by_id'], 'fk_saas_transfers_c71a2bb3e9')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id'], 'fk_saas_transfers_d046da8d50')->references(['id'])->on('saas_transfers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['document_id', 'user_id', 'document_type'], 'fk_saas_transfers_8293563717')->references(['id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['credit_note_id', 'document_id', 'user_id', 'credit_note_document_type'], 'fk_saas_transfers_c481bd880e')->references(['id', 'original_invoice_id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['original_payment_id', 'document_id', 'user_id', 'original_payment_record_type'], 'fk_saas_transfers_dbda9bd81e')->references(['id', 'document_id', 'user_id', 'record_type'])->on('saas_transfers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'document_id', 'user_id', 'record_type'], 'fk_saas_transfers_277e987a88')->references(['id', 'document_id', 'user_id', 'record_type'])->on('saas_transfers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['reversal_of_id', 'document_id', 'user_id', 'original_payment_id', 'record_type'], 'fk_saas_transfers_0a4417454a')->references(['id', 'document_id', 'user_id', 'original_payment_id', 'record_type'])->on('saas_transfers')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->foreign(['created_by_id'], 'fk_media_6fb667974b')->references(['id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('carrier_geo_mappings', function (Blueprint $table): void {
            $table->foreign(['carrier_id'], 'fk_carrier_geo_mappings_e3643dfebd')->references(['id'])->on('shipping_carriers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['geographic_area_id'], 'fk_carrier_geo_mappings_89f600e82e')->references(['id'])->on('geographic_areas')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['geographic_area_id', 'zone_type'], 'fk_carrier_geo_mappings_75c5c7008c')->references(['id', 'type'])->on('geographic_areas')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('pickup_points', function (Blueprint $table): void {
            $table->foreign(['carrier_id'], 'fk_pickup_points_e3643dfebd')->references(['id'])->on('shipping_carriers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['province_id'], 'fk_pickup_points_dd6587e1f7')->references(['id'])->on('geographic_areas')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['municipality_id'], 'fk_pickup_points_4b1b598381')->references(['id'])->on('geographic_areas')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['province_id', 'province_type'], 'fk_pickup_points_1c4b789574')->references(['id', 'type'])->on('geographic_areas')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['municipality_id', 'province_id', 'municipality_type'], 'fk_pickup_points_43469833ce')->references(['id', 'parent_id', 'type'])->on('geographic_areas')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('pickup_points', function (Blueprint $table): void {
            $table->dropForeign('fk_pickup_points_e3643dfebd');
            $table->dropForeign('fk_pickup_points_dd6587e1f7');
            $table->dropForeign('fk_pickup_points_4b1b598381');
            $table->dropForeign('fk_pickup_points_1c4b789574');
            $table->dropForeign('fk_pickup_points_43469833ce');
        });

        Schema::table('carrier_geo_mappings', function (Blueprint $table): void {
            $table->dropForeign('fk_carrier_geo_mappings_e3643dfebd');
            $table->dropForeign('fk_carrier_geo_mappings_89f600e82e');
            $table->dropForeign('fk_carrier_geo_mappings_75c5c7008c');
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->dropForeign('fk_media_6fb667974b');
        });

        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropForeign('fk_saas_transfers_f89d6b6960');
            $table->dropForeign('fk_saas_transfers_2a8e659395');
            $table->dropForeign('fk_saas_transfers_f1937ef24c');
            $table->dropForeign('fk_saas_transfers_d61185a1fb');
            $table->dropForeign('fk_saas_transfers_6aed88530f');
            $table->dropForeign('fk_saas_transfers_2ac3d6fcc8');
            $table->dropForeign('fk_saas_transfers_6fb667974b');
            $table->dropForeign('fk_saas_transfers_6a16de7f77');
            $table->dropForeign('fk_saas_transfers_c71a2bb3e9');
            $table->dropForeign('fk_saas_transfers_d046da8d50');
            $table->dropForeign('fk_saas_transfers_8293563717');
            $table->dropForeign('fk_saas_transfers_c481bd880e');
            $table->dropForeign('fk_saas_transfers_dbda9bd81e');
            $table->dropForeign('fk_saas_transfers_277e987a88');
            $table->dropForeign('fk_saas_transfers_0a4417454a');
        });

        Schema::table('saas_document_deliveries', function (Blueprint $table): void {
            $table->dropForeign('fk_saas_document_deliveries_f89d6b6960');
            $table->dropForeign('fk_saas_document_deliveries_2a8e659395');
            $table->dropForeign('fk_saas_document_deliveries_6fb667974b');
            $table->dropForeign('fk_saas_document_deliveries_6aed88530f');
            $table->dropForeign('fk_saas_document_deliveries_8293563717');
        });

        Schema::table('saas_billing_settings', function (Blueprint $table): void {
            $table->dropForeign('fk_saas_billing_settings_6fb667974b');
            $table->dropForeign('fk_saas_billing_settings_6a16de7f77');
        });

        Schema::table('saas_invoice_lines', function (Blueprint $table): void {
            $table->dropForeign('fk_saas_invoice_lines_2a8e659395');
            $table->dropForeign('fk_saas_invoice_lines_f89d6b6960');
            $table->dropForeign('fk_saas_invoice_lines_ec080ce729');
            $table->dropForeign('fk_saas_invoice_lines_7f3244d890');
            $table->dropForeign('fk_saas_invoice_lines_8293563717');
            $table->dropForeign('fk_saas_invoice_lines_cdd1b4ed69');
            $table->dropForeign('fk_saas_invoice_lines_2dfa389c8b');
        });

        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropForeign('fk_saas_invoices_f89d6b6960');
            $table->dropForeign('fk_saas_invoices_56c8a8bf72');
            $table->dropForeign('fk_saas_invoices_b0a744b4f2');
            $table->dropForeign('fk_saas_invoices_7de4923723');
            $table->dropForeign('fk_saas_invoices_ec080ce729');
            $table->dropForeign('fk_saas_invoices_ac063d431a');
            $table->dropForeign('fk_saas_invoices_1707541b1b');
            $table->dropForeign('fk_saas_invoices_fcc56c4a07');
            $table->dropForeign('fk_saas_invoices_b991245fce');
            $table->dropForeign('fk_saas_invoices_1bae90140d');
            $table->dropForeign('fk_saas_invoices_b70ad3b9df');
            $table->dropForeign('fk_saas_invoices_42bbb6432d');
            $table->dropForeign('fk_saas_invoices_2d79d283e4');
        });

        Schema::table('tenant_schema_deployments', function (Blueprint $table): void {
            $table->dropForeign('fk_tenant_schema_deployments_759b6ffea8');
        });

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropForeign('fk_activity_log_759b6ffea8');
        });

        Schema::table('geographic_areas', function (Blueprint $table): void {
            $table->dropForeign('fk_geographic_areas_1bd5d05f10');
            $table->dropForeign('fk_geographic_areas_7b54484fae');
            $table->dropForeign('fk_geographic_areas_8a88bda689');
        });

        Schema::table('feature_usage', function (Blueprint $table): void {
            $table->dropForeign('fk_feature_usage_f89d6b6960');
            $table->dropForeign('fk_feature_usage_759b6ffea8');
            $table->dropForeign('fk_feature_usage_3de8f04067');
            $table->dropForeign('fk_feature_usage_1bd81732ff');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropForeign('fk_subscriptions_f89d6b6960');
            $table->dropForeign('fk_subscriptions_acf499a627');
            $table->dropForeign('fk_subscriptions_759b6ffea8');
            $table->dropForeign('fk_subscriptions_a427196bc8');
            $table->dropForeign('fk_subscriptions_bc15dd68ce');
            $table->dropForeign('fk_subscriptions_1bd81732ff');
            $table->dropForeign('fk_subscriptions_e56456f06c');
        });

        Schema::table('plan_features', function (Blueprint $table): void {
            $table->dropForeign('fk_plan_features_a427196bc8');
            $table->dropForeign('fk_plan_features_3de8f04067');
        });

        Schema::table('admin_restrictions', function (Blueprint $table): void {
            $table->dropForeign('fk_admin_restrictions_f159b8e5cb');
            $table->dropForeign('fk_admin_restrictions_50d0bf2736');
            $table->dropForeign('fk_admin_restrictions_843f38c8c7');
            $table->dropForeign('fk_admin_restrictions_a28a1cc1dd');
            $table->dropForeign('fk_admin_restrictions_d88b7f676a');
            $table->dropForeign('fk_admin_restrictions_6fb667974b');
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
    }
};
