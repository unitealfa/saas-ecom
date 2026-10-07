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
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('source_version')->nullable();
            $table->string('target_version');
            $table->unsignedTinyInteger('operation')->comment('DeploymentOperationEnum: 1, 2');
            $table->unsignedTinyInteger('status')->default(1)->comment('ExecutionStatusEnum: 1, 2, 3, 4, 5');
            $table->integer('attempt_number');
            $table->string('operation_key');
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('ended_at', 6)->nullable();
            $table->string('error_code')->nullable();
            $table->text('sanitized_error')->nullable();
            $table->char('correlation_id', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->json('runtime_versions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_schema_deployments');
    }
};
