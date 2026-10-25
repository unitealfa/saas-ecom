<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum;
use Carbon\CarbonImmutable;
use Database\Factories\Central\TenantSchemaDeploymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Stancl\Tenancy\Database\Concerns\CentralConnection;


#[Fillable([
    'tenant_id', 'source_version', 'target_version', 'operation', 'status', 'attempt_number',
    'operation_key', 'started_at', 'ended_at', 'error_code', 'sanitized_error',
    'correlation_id', 'runtime_versions',
])]
class TenantSchemaDeployment extends Model
{
    use CentralConnection, HasFactory, HasPublicUuid;

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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }
}