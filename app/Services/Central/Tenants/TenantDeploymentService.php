<?php

namespace App\Services\Central\Tenants;

use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Jobs\Central\MigrateTenantJob;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantDeploymentService
{
    private const int PER_PAGE = 20;

    private const string EVENT_DISPATCHED = 'tenant_deployment_dispatched';

    private const string EVENT_MIGRATION = 'tenant_migration_requested';

    private const string EVENT_RETRIED = 'tenant_deployment_retried';

    /** Une opération en attente ou en cours : une seule à la fois par boutique. */
    private const array OPEN_STATUSES = [StatusEnum::PENDING, StatusEnum::RUNNING];

    /** Filtres de la liste qui portent directement sur une colonne. */
    private const array COLUMN_FILTERS = ['status', 'operation'];

    private const array LIST_COLUMNS = [
        'id', 'uuid', 'tenant_id', 'operation', 'status', 'attempt_number', 'source_version', 'target_version',
        'started_at', 'ended_at', 'created_at', 'updated_at', 'correlation_id', 'operation_key',
    ];

    public function __construct(private AuthorizationMutation $mutations, private TenantSchemaVersions $versions) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TenantSchemaDeployment>
     */
    public function search(User $actor, array $filters): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', TenantSchemaDeployment::class);

        $query = TenantSchemaDeployment::with('tenant')->select(self::LIST_COLUMNS);

        if (filled($filters['search'] ?? null)) {
            $pattern = '%'.addcslashes(trim($filters['search']), '\\%_').'%';

            $query->whereHas('tenant', fn ($query) => $query->withTrashed()->where('shop_name', 'like', $pattern));
        }

        foreach (self::COLUMN_FILTERS as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return $query->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();
    }

    public function provision(User $actor, TenantSchemaDeployment $deployment, string $expected): void
    {
        $this->mutations->run(
            $actor,
            'provision',
            fn () => $this->lock($deployment, $expected),
            function (TenantSchemaDeployment $deployment) use ($actor): void {
                $this->assertLatest($deployment);
                $this->assertNoOtherWork($deployment->tenant_id, $deployment->id);
                $this->versions->through($deployment->target_version);

                $this->dispatch($deployment);
                $this->audit($actor, $deployment, self::EVENT_DISPATCHED);
            },
        );
    }

    public function migrate(User $actor, Tenant $tenant, string $target, string $expected): TenantSchemaDeployment
    {
        return $this->mutations->transaction(function () use ($actor, $tenant, $target, $expected): TenantSchemaDeployment {
            $tenant = $this->lockTenant($tenant);
            Gate::forUser($actor)->authorize('migrate', [TenantSchemaDeployment::class, $tenant]);

            $latest = TenantSchemaDeployment::where('tenant_id', $tenant->id)->latest('id')->lockForUpdate()->firstOrFail();
            $this->assertExpected($latest, $expected);
            $this->assertNoOtherWork($tenant->id);
            $this->assertMigratable($tenant, $target);

            $deployment = $this->reserveMigration($tenant, $target);

            // Une boutique active passe en maintenance pendant la migration ; les autres statuts restent tels quels.
            if ($tenant->status === TenantStatus::ACTIVE) {
                $tenant->status = TenantStatus::PROVISIONING;
                $tenant->save();
            }

            $this->dispatch($deployment);
            $this->audit($actor, $deployment, self::EVENT_MIGRATION);

            return $deployment;
        });
    }

    public function retry(User $actor, TenantSchemaDeployment $deployment, string $expected): TenantSchemaDeployment
    {
        return $this->mutations->run(
            $actor,
            'retry',
            fn () => $this->lock($deployment, $expected),
            fn (TenantSchemaDeployment $deployment): TenantSchemaDeployment => $this->retryLocked($actor, $deployment),
        );
    }

    /** Adaptateur du parcours propriétaire existant, avec sa propre Policy. */
    public function retryProvisioning(User $actor, Tenant $tenant): void
    {
        $this->mutations->run(
            $actor,
            'retryProvisioning',
            fn () => $this->lockTenant($tenant),
            function (Tenant $tenant) use ($actor): void {
                $deployment = TenantSchemaDeployment::where('tenant_id', $tenant->id)->latest('id')->lockForUpdate()->firstOrFail();

                // Une reprise est déjà demandée ou en cours : rien à faire.
                if (in_array($deployment->status, self::OPEN_STATUSES, true)) {
                    return;
                }

                if ($deployment->operation !== OperationEnum::PROVISIONING || $tenant->status !== TenantStatus::PROVISIONING_FAILED) {
                    $this->fail('tenant', 'Cette boutique ne nécessite pas de reprise du provisionnement.');
                }

                $this->retryLocked($actor, $deployment);
            },
        );
    }

    /** Réservation interne : appelée sous verrou par le service ou le worker, jamais par un formulaire. */
    public function reserveRetry(TenantSchemaDeployment $failed): TenantSchemaDeployment
    {
        if ($failed->status !== StatusEnum::FAILED) {
            $this->fail('deployment', 'Seul un échec peut être repris.');
        }

        // La clé retry:<uuid> rend la réservation idempotente : un échec n'a qu'une seule reprise.
        return TenantSchemaDeployment::firstOrCreate(['operation_key' => $this->retryKey($failed)], [
            'tenant_id' => $failed->tenant_id,
            'source_version' => $failed->source_version,
            'target_version' => $failed->target_version,
            'operation' => $failed->operation,
            'status' => StatusEnum::PENDING,
            'attempt_number' => $failed->attempt_number + 1,
            'correlation_id' => $failed->correlation_id,
            'runtime_versions' => $this->versions->runtime(),
        ]);
    }

    private function retryLocked(User $actor, TenantSchemaDeployment $failed): TenantSchemaDeployment
    {
        // Double demande : la reprise déjà réservée est renvoyée sans en créer une seconde.
        $existing = TenantSchemaDeployment::where('operation_key', $this->retryKey($failed))->first();

        if ($existing !== null) {
            return $existing;
        }

        $this->assertLatest($failed);
        $this->assertNoOtherWork($failed->tenant_id);
        $this->versions->through($failed->target_version);

        $deployment = $this->reserveRetry($failed);

        $tenant = $failed->tenant;

        if ($tenant->status === TenantStatus::PROVISIONING_FAILED) {
            $tenant->status = TenantStatus::PROVISIONING;
            $tenant->save();
        }

        $this->dispatch($deployment);
        $this->audit($actor, $deployment, self::EVENT_RETRIED);

        return $deployment;
    }

    private function reserveMigration(Tenant $tenant, string $target): TenantSchemaDeployment
    {
        return TenantSchemaDeployment::create([
            'tenant_id' => $tenant->id,
            'source_version' => $tenant->schema_version,
            'target_version' => $target,
            'operation' => OperationEnum::MIGRATION,
            'status' => StatusEnum::PENDING,
            'attempt_number' => 1,
            'operation_key' => 'migrate:'.$tenant->uuid.':'.Str::uuid(),
            'correlation_id' => (string) Str::uuid(),
            'runtime_versions' => $this->versions->runtime(),
        ]);
    }

    private function dispatch(TenantSchemaDeployment $deployment): void
    {
        $job = $deployment->operation === OperationEnum::PROVISIONING
            ? new ProvisionTenantDatabase($deployment->id)
            : new MigrateTenantJob($deployment->id);

        // La file « database » centrale partage la transaction : le worker ne voit le job qu'après validation.
        // Avec une autre file (Redis…), utiliser afterCommit() à la place.
        dispatch($job->beforeCommit());
    }

    private function lock(TenantSchemaDeployment $deployment, string $expected): TenantSchemaDeployment
    {
        $this->lockTenant($deployment->tenant);

        $current = TenantSchemaDeployment::whereKey($deployment->id)->lockForUpdate()->firstOrFail();
        $this->assertExpected($current, $expected);

        return $current;
    }

    /** Ordre de verrouillage constant : propriétaire, boutique, puis déploiement. */
    private function lockTenant(Tenant $tenant): Tenant
    {
        User::withTrashed()->whereKey($tenant->user_id)->lockForUpdate()->firstOrFail();

        return Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
    }

    private function assertExpected(TenantSchemaDeployment $deployment, string $expected): void
    {
        if (! hash_equals($deployment->managementState(), $expected)) {
            $this->fail('deployment', 'Ce déploiement a changé. Rechargez la page.');
        }
    }

    private function assertLatest(TenantSchemaDeployment $deployment): void
    {
        $newerExists = TenantSchemaDeployment::where('tenant_id', $deployment->tenant_id)
            ->where('id', '>', $deployment->id)
            ->exists();

        if ($newerExists) {
            $this->fail('deployment', 'Une opération plus récente existe pour cette boutique.');
        }
    }

    private function assertNoOtherWork(int $tenantId, ?int $except = null): void
    {
        $busy = TenantSchemaDeployment::where('tenant_id', $tenantId)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))
            ->whereIn('status', self::OPEN_STATUSES)
            ->exists();

        if ($busy) {
            $this->fail('deployment', 'Une opération est déjà en attente ou en cours pour cette boutique.');
        }
    }

    /** La version visée doit être la dernière connue et plus récente que celle de la boutique ; sa base doit exister. */
    private function assertMigratable(Tenant $tenant, string $target): void
    {
        if ($target !== $this->versions->latest() || strcmp((string) $tenant->schema_version, $target) >= 0) {
            $this->fail('target_version', 'Choisissez une version prévue plus récente que la version actuelle.');
        }

        $this->versions->through((string) $tenant->schema_version);

        if (! $tenant->database()->manager()->databaseExists($tenant->database()->getName())) {
            $this->fail('deployment', 'La base de cette boutique est absente.');
        }
    }

    private function retryKey(TenantSchemaDeployment $failed): string
    {
        return 'retry:'.$failed->uuid;
    }

    private function audit(User $actor, TenantSchemaDeployment $deployment, string $event): void
    {
        activity()->causedBy($actor)->performedOn($deployment)->event($event)
            ->withProperties([
                'tenant_uuid' => $deployment->tenant->uuid,
                'operation' => $deployment->operation->value,
                'target_version' => $deployment->target_version,
                'attempt_number' => $deployment->attempt_number,
            ])
            ->log('Opération technique de la boutique demandée.');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}