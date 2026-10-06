<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->unique(['id', 'user_id', 'record_type'], 'uq_subscriptions_8ef95e0c44');
            $table->unique(['id', 'parent_subscription_id', 'user_id', 'record_type'], 'uq_subscriptions_f5e32fea0c');
            $table->unique(['user_id', 'active_owner_slot'], 'uq_subscriptions_f59d836b7a');
            $table->index(['record_type', 'user_id', 'status'], 'ix_subscriptions_c0c4780fba');
            $table->index(['parent_subscription_id', 'installment_status', 'due_at'], 'ix_subscriptions_2606f12b0d');
            $table->index(['parent_subscription_id', 'user_id', 'parent_record_type'], 'ix_subscriptions_e56456f06c');
            $table->index(['tenant_id', 'user_id'], 'ix_subscriptions_1bd81732ff');
            $table->index(['plan_id'], 'ix_subscriptions_a427196bc8');
            $table->index(['assigned_by_id'], 'ix_subscriptions_bc15dd68ce');
        });

        Schema::table('geographic_areas', function (Blueprint $table): void {
            $table->unique(['id', 'country_id', 'type'], 'uq_geographic_areas_a1f59ca9b7');
            $table->unique(['id', 'type'], 'uq_geographic_areas_a31b24084c');
            $table->unique(['id', 'parent_id', 'type'], 'uq_geographic_areas_c2b88c4bed');
            $table->unique(['country_id', 'type', 'parent_key', 'code'], 'uq_geographic_areas_83fb06bf15');
            $table->index(['country_id', 'type', 'parent_id', 'is_active'], 'ix_geographic_areas_3182351b15');
            $table->index(['parent_id', 'country_id', 'parent_type'], 'ix_geographic_areas_8a88bda689');
        });

        Schema::table('saas_billing_settings', function (Blueprint $table): void {
            $table->unique(['id', 'record_type'], 'uq_saas_billing_settings_8e50b89e8a');
            $table->unique(['id', 'document_type', 'fiscal_year', 'record_type'], 'uq_saas_billing_settings_461178d473');
            $table->unique(['document_type', 'fiscal_year', 'sequence_slot'], 'uq_saas_billing_settings_c8aa6027ef');
            $table->unique(['code', 'version'], 'uq_saas_billing_settings_f5b433d863');
            $table->index(['code', 'policy_status', 'effective_at'], 'ix_saas_billing_settings_a2842e9e62');
            $table->index(['correlation_id'], 'ix_saas_billing_settings_c787c4c20c');
            $table->index(['created_by_id'], 'ix_saas_billing_settings_6fb667974b');
            $table->index(['validated_by_id'], 'ix_saas_billing_settings_6a16de7f77');
        });

        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->unique(['id', 'user_id', 'document_type'], 'uq_saas_invoices_55c06b53b4');
            $table->unique(['id', 'installment_id', 'subscription_id', 'user_id', 'document_type'], 'uq_saas_invoices_8dc1fbcf1c');
            $table->unique(['id', 'original_invoice_id', 'user_id', 'document_type'], 'uq_saas_invoices_a658339d67');
            $table->unique(['sequence_id', 'sequence_number'], 'uq_saas_invoices_ba5a89d2cf');
            $table->index(['original_invoice_id', 'installment_id', 'subscription_id', 'user_id', 'original_invoice_document_type'], 'ix_saas_invoices_2d79d283e4');
            $table->index(['user_id', 'document_type', 'issued_at', 'id'], 'ix_saas_invoices_ae0052dd0b');
            $table->index(['installment_id', 'subscription_id', 'user_id', 'installment_record_type'], 'ix_saas_invoices_b991245fce');
            $table->index(['sequence_id', 'document_type', 'fiscal_year', 'sequence_record_type'], 'ix_saas_invoices_b70ad3b9df');
            $table->index(['installment_id', 'document_type', 'status'], 'ix_saas_invoices_0f27c506e7');
            $table->index(['original_invoice_id', 'status', 'id'], 'ix_saas_invoices_913fccf823');
            $table->index(['subscription_id', 'user_id', 'subscription_record_type'], 'ix_saas_invoices_fcc56c4a07');
            $table->index(['original_invoice_id', 'user_id', 'original_invoice_document_type'], 'ix_saas_invoices_42bbb6432d');
            $table->index(['billing_rule_id', 'billing_rule_record_type'], 'ix_saas_invoices_1bae90140d');
            $table->index(['document_media_id'], 'ix_saas_invoices_1707541b1b');
        });

        Schema::table('saas_invoice_lines', function (Blueprint $table): void {
            $table->unique(['id', 'document_id', 'user_id', 'document_type'], 'uq_saas_invoice_lines_77a3b1856b');
            $table->unique(['document_id', 'line_number'], 'uq_saas_invoice_lines_533c361e24');
            $table->unique(['document_id', 'original_invoice_line_id'], 'uq_saas_invoice_lines_c60d2137ae');
            $table->index(['document_id', 'original_invoice_id', 'user_id', 'document_type'], 'ix_saas_invoice_lines_cdd1b4ed69');
            $table->index(['original_invoice_line_id', 'original_invoice_id', 'user_id', 'original_line_document_type'], 'ix_saas_invoice_lines_2dfa389c8b');
            $table->index(['document_id', 'user_id', 'document_type'], 'ix_saas_invoice_lines_8293563717');
            $table->index(['original_invoice_line_id', 'document_id'], 'ix_saas_invoice_lines_cfc183580c');
            $table->index(['user_id'], 'ix_saas_invoice_lines_f89d6b6960');
            $table->index(['original_invoice_id'], 'ix_saas_invoice_lines_ec080ce729');
        });

        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->unique(['id', 'document_id', 'user_id', 'record_type'], 'uq_saas_transfers_c2fd259498');
            $table->unique(['id', 'document_id', 'user_id', 'original_payment_id', 'record_type'], 'uq_saas_transfers_892616667f');
            $table->unique(['reversal_of_id'], 'uq_saas_transfers_d046da8d50');
            $table->index(['user_id', 'record_type', 'transfer_status', 'created_at', 'id'], 'ix_saas_transfers_16b7dff1cc');
            $table->index(['reversal_of_id', 'document_id', 'user_id', 'original_payment_id', 'record_type'], 'ix_saas_transfers_0a4417454a');
            $table->index(['document_id', 'record_type', 'transfer_status', 'id'], 'ix_saas_transfers_00834cb1b8');
            $table->index(['original_payment_id', 'record_type', 'transfer_status', 'id'], 'ix_saas_transfers_2622b7c9f3');
            $table->index(['credit_note_id', 'record_type', 'transfer_status', 'id'], 'ix_saas_transfers_5f908882cf');
            $table->index(['credit_note_id', 'document_id', 'user_id', 'credit_note_document_type'], 'ix_saas_transfers_c481bd880e');
            $table->index(['original_payment_id', 'document_id', 'user_id', 'original_payment_record_type'], 'ix_saas_transfers_dbda9bd81e');
            $table->index(['reversal_of_id', 'document_id', 'user_id', 'record_type'], 'ix_saas_transfers_277e987a88');
            $table->index(['document_id', 'user_id', 'document_type'], 'ix_saas_transfers_8293563717');
            $table->index(['correlation_id'], 'ix_saas_transfers_c787c4c20c');
            $table->index(['proof_media_id'], 'ix_saas_transfers_6aed88530f');
            $table->index(['source_proof_media_id'], 'ix_saas_transfers_2ac3d6fcc8');
            $table->index(['created_by_id'], 'ix_saas_transfers_6fb667974b');
            $table->index(['validated_by_id'], 'ix_saas_transfers_6a16de7f77');
            $table->index(['performed_by_id'], 'ix_saas_transfers_c71a2bb3e9');
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

        Schema::table('admin_restrictions', function (Blueprint $table): void {
            $table->unique(['admin_id', 'permission_id', 'normalized_target_type', 'normalized_target_id', 'effect', 'active_slot'], 'uq_admin_restrictions_9a8bd22048');
            $table->index(['permission_id'], 'ix_admin_restrictions_50d0bf2736');
            $table->index(['target_tenant_id'], 'ix_admin_restrictions_843f38c8c7');
            $table->index(['target_user_id'], 'ix_admin_restrictions_a28a1cc1dd');
            $table->index(['target_role_id'], 'ix_admin_restrictions_d88b7f676a');
            $table->index(['created_by_id'], 'ix_admin_restrictions_6fb667974b');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->unique(['code', 'version'], 'uq_plans_f5b433d863');
        });

        Schema::table('plan_features', function (Blueprint $table): void {
            $table->unique(['plan_id', 'feature_id'], 'uq_plan_features_b5beb535fd');
            $table->index(['feature_id'], 'ix_plan_features_3de8f04067');
        });

        Schema::table('tenant_schema_deployments', function (Blueprint $table): void {
            $table->unique(['operation_key'], 'uq_tenant_schema_deployments_c8ff3469da');
            $table->index(['tenant_id', 'created_at'], 'ix_tenant_schema_deployments_eb4657a8bf');
        });

        Schema::table('carrier_geo_mappings', function (Blueprint $table): void {
            $table->unique(['carrier_id', 'geographic_area_id'], 'uq_carrier_geo_mappings_5e037a4a2a');
            $table->index(['geographic_area_id', 'zone_type'], 'ix_carrier_geo_mappings_75c5c7008c');
        });

        Schema::table('pickup_points', function (Blueprint $table): void {
            $table->unique(['carrier_id', 'external_code'], 'uq_pickup_points_c5a57a212b');
            $table->index(['municipality_id', 'province_id', 'municipality_type'], 'ix_pickup_points_43469833ce');
            $table->index(['province_id', 'province_type'], 'ix_pickup_points_1c4b789574');
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->index(['user_id', 'deleted_at', 'status'], 'ix_tenants_e20f1a9a18');
        });

        Schema::table('saas_document_deliveries', function (Blueprint $table): void {
            $table->index(['delivery_status', 'next_attempt_at', 'id'], 'ix_saas_document_deliveries_23e581b097');
            $table->index(['document_id', 'created_at', 'id'], 'ix_saas_document_deliveries_67467a6653');
            $table->index(['document_id', 'user_id', 'document_type'], 'ix_saas_document_deliveries_8293563717');
            $table->index(['correlation_id'], 'ix_saas_document_deliveries_c787c4c20c');
            $table->index(['user_id'], 'ix_saas_document_deliveries_f89d6b6960');
            $table->index(['created_by_id'], 'ix_saas_document_deliveries_6fb667974b');
            $table->index(['proof_media_id'], 'ix_saas_document_deliveries_6aed88530f');
        });

        Schema::table('model_has_roles', function (Blueprint $table): void {
            $table->index(['model_id', 'model_type'], 'ix_model_has_roles_941e11a770');
        });

        Schema::table('model_has_permissions', function (Blueprint $table): void {
            $table->index(['model_id', 'model_type'], 'ix_model_has_permissions_941e11a770');
        });

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->index(['subject_type', 'subject_id'], 'ix_activity_log_bdcae89d30');
            $table->index(['causer_type', 'causer_id'], 'ix_activity_log_863811f917');
            $table->index(['correlation_id'], 'ix_activity_log_c787c4c20c');
            $table->index(['tenant_id'], 'ix_activity_log_759b6ffea8');
        });

        Schema::table('feature_usage', function (Blueprint $table): void {
            $table->index(['tenant_id', 'user_id'], 'ix_feature_usage_1bd81732ff');
            $table->index(['user_id'], 'ix_feature_usage_f89d6b6960');
            $table->index(['feature_id'], 'ix_feature_usage_3de8f04067');
        });

        Schema::table('role_has_permissions', function (Blueprint $table): void {
            $table->index(['role_id'], 'ix_role_has_permissions_26525afb8b');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex('ix_media_6fb667974b');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_c71a2bb3e9');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_6a16de7f77');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_6fb667974b');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_2ac3d6fcc8');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_6aed88530f');
        });
        Schema::table('saas_document_deliveries', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_document_deliveries_6aed88530f');
        });
        Schema::table('saas_document_deliveries', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_document_deliveries_6fb667974b');
        });
        Schema::table('saas_document_deliveries', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_document_deliveries_f89d6b6960');
        });
        Schema::table('saas_billing_settings', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_billing_settings_6a16de7f77');
        });
        Schema::table('saas_billing_settings', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_billing_settings_6fb667974b');
        });
        Schema::table('saas_invoice_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoice_lines_ec080ce729');
        });
        Schema::table('saas_invoice_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoice_lines_f89d6b6960');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoices_1707541b1b');
        });
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex('ix_activity_log_759b6ffea8');
        });
        Schema::table('feature_usage', function (Blueprint $table): void {
            $table->dropIndex('ix_feature_usage_3de8f04067');
        });
        Schema::table('feature_usage', function (Blueprint $table): void {
            $table->dropIndex('ix_feature_usage_f89d6b6960');
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('ix_subscriptions_bc15dd68ce');
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('ix_subscriptions_a427196bc8');
        });
        Schema::table('plan_features', function (Blueprint $table): void {
            $table->dropIndex('ix_plan_features_3de8f04067');
        });
        Schema::table('admin_restrictions', function (Blueprint $table): void {
            $table->dropIndex('ix_admin_restrictions_6fb667974b');
        });
        Schema::table('admin_restrictions', function (Blueprint $table): void {
            $table->dropIndex('ix_admin_restrictions_d88b7f676a');
        });
        Schema::table('admin_restrictions', function (Blueprint $table): void {
            $table->dropIndex('ix_admin_restrictions_a28a1cc1dd');
        });
        Schema::table('admin_restrictions', function (Blueprint $table): void {
            $table->dropIndex('ix_admin_restrictions_843f38c8c7');
        });
        Schema::table('admin_restrictions', function (Blueprint $table): void {
            $table->dropIndex('ix_admin_restrictions_50d0bf2736');
        });
        Schema::table('role_has_permissions', function (Blueprint $table): void {
            $table->dropIndex('ix_role_has_permissions_26525afb8b');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_c787c4c20c');
        });
        Schema::table('saas_document_deliveries', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_document_deliveries_c787c4c20c');
        });
        Schema::table('saas_billing_settings', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_billing_settings_c787c4c20c');
        });
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex('ix_activity_log_c787c4c20c');
        });
        Schema::table('pickup_points', function (Blueprint $table): void {
            $table->dropIndex('ix_pickup_points_1c4b789574');
        });
        Schema::table('carrier_geo_mappings', function (Blueprint $table): void {
            $table->dropIndex('ix_carrier_geo_mappings_75c5c7008c');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoices_1bae90140d');
        });
        Schema::table('feature_usage', function (Blueprint $table): void {
            $table->dropIndex('ix_feature_usage_1bd81732ff');
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('ix_subscriptions_1bd81732ff');
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
        Schema::table('saas_invoice_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoice_lines_cfc183580c');
        });
        Schema::table('tenant_schema_deployments', function (Blueprint $table): void {
            $table->dropIndex('ix_tenant_schema_deployments_eb4657a8bf');
        });
        Schema::table('pickup_points', function (Blueprint $table): void {
            $table->dropIndex('ix_pickup_points_43469833ce');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoices_42bbb6432d');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_8293563717');
        });
        Schema::table('saas_document_deliveries', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_document_deliveries_8293563717');
        });
        Schema::table('saas_invoice_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoice_lines_8293563717');
        });
        Schema::table('geographic_areas', function (Blueprint $table): void {
            $table->dropIndex('ix_geographic_areas_8a88bda689');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoices_fcc56c4a07');
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('ix_subscriptions_e56456f06c');
        });
        Schema::table('saas_document_deliveries', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_document_deliveries_67467a6653');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoices_913fccf823');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoices_0f27c506e7');
        });
        Schema::table('saas_billing_settings', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_billing_settings_a2842e9e62');
        });
        Schema::table('saas_document_deliveries', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_document_deliveries_23e581b097');
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('ix_subscriptions_2606f12b0d');
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('ix_subscriptions_c0c4780fba');
        });
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex('ix_tenants_e20f1a9a18');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_277e987a88');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_dbda9bd81e');
        });
        Schema::table('saas_invoice_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoice_lines_2dfa389c8b');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_c481bd880e');
        });
        Schema::table('saas_invoice_lines', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoice_lines_cdd1b4ed69');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoices_b70ad3b9df');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoices_b991245fce');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_5f908882cf');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_2622b7c9f3');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_00834cb1b8');
        });
        Schema::table('geographic_areas', function (Blueprint $table): void {
            $table->dropIndex('ix_geographic_areas_3182351b15');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoices_ae0052dd0b');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_0a4417454a');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_invoices_2d79d283e4');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropIndex('ix_saas_transfers_16b7dff1cc');
        });
        Schema::table('pickup_points', function (Blueprint $table): void {
            $table->dropUnique('uq_pickup_points_c5a57a212b');
        });
        Schema::table('carrier_geo_mappings', function (Blueprint $table): void {
            $table->dropUnique('uq_carrier_geo_mappings_5e037a4a2a');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_transfers_d046da8d50');
        });
        Schema::table('saas_billing_settings', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_billing_settings_f5b433d863');
        });
        Schema::table('saas_billing_settings', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_billing_settings_c8aa6027ef');
        });
        Schema::table('saas_invoice_lines', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_invoice_lines_c60d2137ae');
        });
        Schema::table('saas_invoice_lines', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_invoice_lines_533c361e24');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_invoices_ba5a89d2cf');
        });
        Schema::table('tenant_schema_deployments', function (Blueprint $table): void {
            $table->dropUnique('uq_tenant_schema_deployments_c8ff3469da');
        });
        Schema::table('geographic_areas', function (Blueprint $table): void {
            $table->dropUnique('uq_geographic_areas_83fb06bf15');
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropUnique('uq_subscriptions_f59d836b7a');
        });
        Schema::table('plan_features', function (Blueprint $table): void {
            $table->dropUnique('uq_plan_features_b5beb535fd');
        });
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropUnique('uq_plans_f5b433d863');
        });
        Schema::table('admin_restrictions', function (Blueprint $table): void {
            $table->dropUnique('uq_admin_restrictions_9a8bd22048');
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
        Schema::table('geographic_areas', function (Blueprint $table): void {
            $table->dropUnique('uq_geographic_areas_c2b88c4bed');
        });
        Schema::table('geographic_areas', function (Blueprint $table): void {
            $table->dropUnique('uq_geographic_areas_a31b24084c');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_transfers_892616667f');
        });
        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_transfers_c2fd259498');
        });
        Schema::table('saas_invoice_lines', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_invoice_lines_77a3b1856b');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_invoices_a658339d67');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_invoices_8dc1fbcf1c');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_invoices_55c06b53b4');
        });
        Schema::table('saas_billing_settings', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_billing_settings_461178d473');
        });
        Schema::table('saas_billing_settings', function (Blueprint $table): void {
            $table->dropUnique('uq_saas_billing_settings_8e50b89e8a');
        });
        Schema::table('geographic_areas', function (Blueprint $table): void {
            $table->dropUnique('uq_geographic_areas_a1f59ca9b7');
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropUnique('uq_subscriptions_f5e32fea0c');
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropUnique('uq_subscriptions_8ef95e0c44');
        });
    }
};
