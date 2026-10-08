<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX uq_feature_usage_period ON feature_usage (user_id, feature_id, (COALESCE(tenant_id, 0)), period_starts_at)');
        DB::statement('CREATE UNIQUE INDEX uq_tenant_deployment_running ON tenant_schema_deployments (tenant_id, (CASE WHEN status = 2 THEN 1 ELSE NULL END))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX uq_feature_usage_period ON feature_usage');
        DB::statement('DROP INDEX uq_tenant_deployment_running ON tenant_schema_deployments');
    }
};
