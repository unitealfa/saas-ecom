<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum;
use Carbon\CarbonImmutable;
use Database\Factories\Central\TenantSchemaDeploymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property StatusEnum $status
 * @property OperationEnum $operation
 * @property CarbonImmutable|null $started_at
 */
class TenantSchemaDeployment extends Model
{
    /** @use HasFactory<TenantSchemaDeploymentFactory> */
    use CentralConnection, HasFactory, HasPublicUuid;

    protected $fillable = ['tenant_id', 'source_version', 'target_version', 'operation', 'status', 'attempt_number', 'operation_key', 'started_at', 'ended_at', 'error_code', 'sanitized_error', 'correlation_id', 'runtime_versions'];

    protected static function newFactory(): TenantSchemaDeploymentFactory
    {
        return TenantSchemaDeploymentFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'operation' => OperationEnum::class,
            'status' => StatusEnum::class,
            'attempt_number' => 'integer',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'runtime_versions' => 'array',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
