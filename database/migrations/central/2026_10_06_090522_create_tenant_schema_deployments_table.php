<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_schema_deployments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('tenant_id')->constrained('tenants', indexName: 'fk_tenant_schema_deployments_759b6ffea8')->restrictOnDelete()->restrictOnUpdate();
            $table->string('source_version')->nullable();
            $table->string('target_version');
            $table->unsignedTinyInteger('operation')->comment('DeploymentOperationEnum: 1, 2');
            $table->unsignedTinyInteger('status')->default(1)->comment('ExecutionStatusEnum: 1, 2, 3, 4, 5');
            $table->integer('attempt_number');
            $table->string('operation_key');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('sanitized_error')->nullable();
            $table->uuid('correlation_id');
            $table->json('runtime_versions');
            $table->timestamps();

            $table->unique(['operation_key'], 'uq_tenant_schema_deployments_c8ff3469da');
            $table->index(['tenant_id', 'created_at'], 'ix_tenant_schema_deployments_eb4657a8bf');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_schema_deployments');
    }
};
