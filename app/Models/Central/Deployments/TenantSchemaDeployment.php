<?php

namespace App\Models\Central\Deployments;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum;
use App\Models\Central\Tenants\Tenant;
use Database\Factories\Central\TenantSchemaDeploymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property OperationEnum $operation
 * @property StatusEnum $status
 */
#[Fillable([
    'tenant_id', 'source_version', 'target_version', 'operation', 'status', 'attempt_number',
    'operation_key', 'started_at', 'ended_at', 'error_code', 'sanitized_error',
    'correlation_id', 'runtime_versions',
])]
#[UseFactory(TenantSchemaDeploymentFactory::class)]
class TenantSchemaDeployment extends Model
{
    /** @use HasFactory<TenantSchemaDeploymentFactory> */
    use CentralConnection, HasFactory, HasPublicUuid;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function managementState(): string
    {
        $state = $this->only(['uuid', 'tenant_id', 'source_version', 'target_version', 'operation', 'status',
            'attempt_number', 'operation_key', 'correlation_id', 'started_at', 'ended_at', 'error_code',
            'sanitized_error', 'runtime_versions', 'created_at', 'updated_at']);
        foreach ($state as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $state[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    /** Ce qui identifie un déploiement : figé après la création. */
    private const array IMMUTABLE_COLUMNS = [
        'tenant_id', 'source_version', 'target_version', 'operation', 'operation_key', 'correlation_id',
    ];

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

    protected static function booted(): void
    {
        static::updating(function (self $deployment): void {
            if ($deployment->isDirty(self::IMMUTABLE_COLUMNS)) {
                throw new LogicException('Deployment identity is immutable.');
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }
}
