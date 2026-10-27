<?php

namespace App\Services\Central\Tenants;

use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Enums\Central\Tenants\StatusEnum;
use App\Enums\Central\Users\StatusEnum as UserStatus;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Feature;
use App\Models\Central\PlanFeature;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Subscriptions\DefaultSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class TenantStatusService
{
    private const string SHOPS_FEATURE = 'shops';

    private const array ACTIONS = ['suspend', 'activate', 'archive'];

    public function __construct(
        private AuthorizationMutation $mutations,
        private DefaultSubscription $subscriptions,
        private TenantProfileProjection $profiles,
        private CentralUserAccess $access,
    ) {}

    public function completeProvisioning(Tenant $tenant, int $deploymentId): void
    {
        $this->mutations->transaction(function () use ($tenant, $deploymentId): void {
            [$owner, $shops, $tenant] = $this->lockOwnerShops($tenant);
            $deployment = TenantSchemaDeployment::whereKey($deploymentId)->lockForUpdate()->firstOrFail();

            if ($deployment->tenant_id !== $tenant?->id || $deployment->status !== DeploymentStatus::RUNNING) {
                throw new LogicException('The provisioning reservation is no longer current.');
            }

            $deployment->update(['status' => DeploymentStatus::SUCCEEDED, 'ended_at' => now()]);

            if (! in_array($tenant->status, [StatusEnum::PROVISIONING, StatusEnum::PROVISIONING_FAILED], true)) {
                return;
            }

            $this->profiles->assertPrepared($tenant, $owner);

            try {
                $this->assertOwnerEligible($owner);
            } catch (ValidationException) {
                $tenant->status = StatusEnum::INACTIVE;
                $tenant->save();

                activity()->performedOn($tenant)->event('tenant_provisioned_blocked')
                    ->withProperties(['reason' => 'owner_ineligible', 'deployment_uuid' => $deployment->uuid])
                    ->log('Boutique initialisée, accès bloqué par l’état du propriétaire.');

                return;
            }

            $grant = $this->shopsGrant($this->subscriptions->ensure($owner), required: false);

            if ($grant === null || ! $grant->is_active) {
                $tenant->forceFill(['status' => StatusEnum::OVER_QUOTA, 'over_quota_since_at' => now()])->save();
            } else {
                $tenant->status = StatusEnum::ACTIVE;
                $this->rebalance($owner, $owner, $shops, null, null);
            }

            activity()->performedOn($tenant)->event($deployment->operation === OperationEnum::MIGRATION ? 'tenant_schema_migrated' : 'tenant_provisioned')
                ->withProperties(['status' => $tenant->status->value, 'deployment_uuid' => $deployment->uuid,
                    'source_version' => $deployment->source_version, 'target_version' => $deployment->target_version])
                ->log('Opération technique terminée et état d’accès calculé automatiquement.');
        });
    }

    public function change(User $actor, Tenant $tenant, string $action, StatusEnum $expected, ?string $reason): void
    {
        abort_unless(in_array($action, self::ACTIONS, true), 403);

        $this->mutations->transaction(function () use ($actor, $tenant, $action, $expected, $reason): void {
            [$owner, $shops, $tenant] = $this->lockOwnerShops($tenant);
            abort_if($tenant === null, 404);

            Gate::forUser($actor)->authorize($action, $tenant);

            if ($tenant->status !== $expected) {
                throw ValidationException::withMessages(['tenant' => 'L’état a changé. Rechargez la boutique avant de continuer.']);
            }

            $deployments = TenantSchemaDeployment::where('tenant_id', $tenant->id)
                ->whereIn('status', [DeploymentStatus::PENDING, DeploymentStatus::RUNNING])
                ->orderBy('id')->lockForUpdate()->get();

            if ($deployments->contains('status', DeploymentStatus::RUNNING)) {
                throw ValidationException::withMessages(['tenant' => 'Une opération technique est en cours. Attendez sa fin.']);
            }

            $before = $tenant->status;

            $changed = match ($action) {
                'archive' => $this->archive($tenant, $deployments),
                'suspend' => $this->suspend($tenant, $deployments),
                'activate' => $this->activate($actor, $owner, $shops, $tenant, $deployments, $reason),
            };

            if (! $changed) {
                return;
            }

            activity()->causedBy($actor)->performedOn($tenant)->event('tenant_'.$action)
                ->withProperties(['before' => ['status' => $before->value], 'after' => ['status' => $tenant->status->value], 'reason' => $reason])
                ->log('État administratif de la boutique modifié.');
        });
    }

    /** @param array<string, int|string> $priorities */
    public function priorities(User $actor, Tenant $tenant, array $priorities, ?string $reason): void
    {
        $this->mutations->transaction(function () use ($actor, $tenant, $priorities, $reason): void {
            [$owner, $shops, $tenant] = $this->lockOwnerShops($tenant);
            abort_if($tenant === null, 404);

            Gate::forUser($actor)->authorize('updatePriorities', $tenant);

            $priorities = array_map(intval(...), $priorities);
            $expected = $shops->pluck('uuid')->sort()->values()->all();
            $provided = array_keys($priorities);
            sort($provided);
            $ordered = array_values($priorities);
            sort($ordered);

            if ($provided !== $expected || $ordered !== range(1, $shops->count())) {
                throw ValidationException::withMessages(['priorities' => 'Classez toutes les boutiques de ce propriétaire une seule fois, de 1 au nombre de boutiques.']);
            }

            $this->assertOwnerEligible($owner);

            $before = $shops->mapWithKeys(fn (Tenant $shop): array => [$shop->uuid => $shop->activation_priority])->all();

            foreach ($shops as $shop) {
                $shop->activation_priority = $priorities[$shop->uuid];
                $shop->save();
            }

            $this->rebalance($actor, $owner, $shops, null, $reason);

            activity()->causedBy($actor)->performedOn($tenant)->event('tenant_priorities_updated')
                ->withProperties(['before' => $before, 'after' => $priorities, 'reason' => $reason])
                ->log('Priorités des boutiques du propriétaire modifiées.');
        });
    }

    /** @return array{User, Collection<int, Tenant>, Tenant|null} */
    private function lockOwnerShops(Tenant $tenant): array
    {
        $owner = User::withTrashed()->whereKey($tenant->user_id)->lockForUpdate()->firstOrFail();
        /** @var Collection<int, Tenant> $shops */
        $shops = Tenant::where('user_id', $owner->id)->orderBy('id')->lockForUpdate()->get();
        $current = $shops->first(fn (Tenant $shop): bool => $shop->id === $tenant->id && $shop->uuid === $tenant->uuid);

        return [$owner, $shops, $current];
    }

    /** @param Collection<int, TenantSchemaDeployment> $deployments */
    private function archive(Tenant $tenant, Collection $deployments): bool
    {
        foreach ($deployments as $deployment) {
            $deployment->update(['status' => DeploymentStatus::CANCELLED, 'ended_at' => now()]);
        }

        $tenant->forceFill(['status' => StatusEnum::DELETED, 'deleted_at' => now(), 'over_quota_since_at' => null])->save();

        return true;
    }

    /** @param Collection<int, TenantSchemaDeployment> $deployments */
    private function suspend(Tenant $tenant, Collection $deployments): bool
    {
        if ($deployments->isNotEmpty() || ! filled($tenant->schema_version) || $tenant->provisioned_at === null
            || in_array($tenant->status, [StatusEnum::PROVISIONING_FAILED, StatusEnum::DELETED], true)) {
            throw ValidationException::withMessages(['tenant' => 'Cette boutique n’est pas encore préparée.']);
        }

        if ($tenant->status === StatusEnum::SUSPENDED) {
            return false;
        }

        $tenant->forceFill(['status' => StatusEnum::SUSPENDED, 'over_quota_since_at' => null])->save();

        return true;
    }

    /**
     * @param  Collection<int, Tenant>  $shops
     * @param  Collection<int, TenantSchemaDeployment>  $deployments
     */
    private function activate(User $actor, User $owner, Collection $shops, Tenant $tenant, Collection $deployments, ?string $reason): bool
    {
        if (! in_array($tenant->status, [StatusEnum::INACTIVE, StatusEnum::SUSPENDED, StatusEnum::OVER_QUOTA], true)) {
            throw ValidationException::withMessages(['tenant' => 'L’activation initiale est automatique après la préparation technique.']);
        }

        if ($deployments->isNotEmpty()) {
            throw ValidationException::withMessages(['tenant' => 'Terminez la préparation technique avant l’activation.']);
        }

        $this->assertOwnerEligible($owner);
        $this->profiles->assertPrepared($tenant, $owner);

        $tenant->status = StatusEnum::ACTIVE;
        $this->rebalance($actor, $owner, $shops, $tenant, $reason);

        return true;
    }

    private function assertOwnerEligible(User $owner): void
    {
        if ($owner->trashed() || $owner->status !== UserStatus::ACTIVE || $this->access->isStaff($owner)
            || $owner->email_verified_at === null || $owner->legal_verification_status !== VerificationStatusEnum::VERIFIED
            || $owner->legal_verified_at === null || $owner->legal_profile_version === null) {
            throw ValidationException::withMessages(['tenant' => 'Le propriétaire doit être un commerçant actif avec son e-mail et son dossier professionnel vérifiés.']);
        }
    }

    private function shopsGrant(Subscription $subscription, bool $required = true): ?PlanFeature
    {
        $query = Feature::where('code', self::SHOPS_FEATURE)->active();
        $feature = $required ? $query->firstOrFail() : $query->first();

        return $feature === null ? null : PlanFeature::where('plan_id', $subscription->plan_id)
            ->where('feature_id', $feature->id)->lockForUpdate()->first();
    }

    /** @param Collection<int, Tenant> $shops */
    private function rebalance(User $actor, User $owner, Collection $shops, ?Tenant $requested, ?string $reason): void
    {
        $grant = $this->shopsGrant($this->subscriptions->ensure($owner, $actor));

        if ($grant === null || ! $grant->is_active) {
            throw ValidationException::withMessages(['tenant' => 'L’offre applicable ne permet pas d’activer une boutique.']);
        }

        $busy = TenantSchemaDeployment::whereIn('tenant_id', $shops->pluck('id'))
            ->whereIn('status', [DeploymentStatus::PENDING, DeploymentStatus::RUNNING])->lockForUpdate()->pluck('tenant_id');

        $eligible = $shops->filter(fn (Tenant $shop): bool => in_array($shop->status, [StatusEnum::ACTIVE, StatusEnum::OVER_QUOTA], true)
            && filled($shop->schema_version) && $shop->provisioned_at !== null && ! $busy->contains($shop->id))
            ->sortBy(fn (Tenant $shop): array => [
                $shop->activation_priority === null ? 1 : 0,
                $shop->activation_priority ?? PHP_INT_MAX,
                $shop->is_primary ? 0 : 1,
                $shop->created_at->timestamp,
                $shop->uuid,
            ])->values();

        if ($shops->contains(fn (Tenant $shop): bool => $shop->status === StatusEnum::ACTIVE && ! $eligible->contains('id', $shop->id))) {
            throw ValidationException::withMessages(['tenant' => 'Une boutique active a une préparation technique incomplète.']);
        }

        $selected = $grant->limit === null ? $eligible : $eligible->take($grant->limit);

        if ($requested !== null && ! $selected->contains('id', $requested->id)) {
            throw ValidationException::withMessages(['tenant' => 'Le quota est atteint. Modifiez les priorités pour choisir les boutiques actives.']);
        }

        foreach ($selected as $shop) {
            $this->profiles->assertPrepared($shop, $owner);
        }

        foreach ($eligible as $shop) {
            $before = $shop->status;
            $status = $selected->contains('id', $shop->id) ? StatusEnum::ACTIVE : StatusEnum::OVER_QUOTA;
            $shop->status = $status;
            $shop->over_quota_since_at = $status === StatusEnum::OVER_QUOTA ? ($shop->over_quota_since_at ?? now()) : null;

            if (! $shop->isDirty()) {
                continue;
            }

            $shop->save();

            if ($requested === null || $requested->id !== $shop->id) {
                activity()->causedBy($actor)->performedOn($shop)->event('tenant_quota_rebalanced')
                    ->withProperties(['before' => ['status' => $before->value], 'after' => ['status' => $status->value], 'reason' => $reason])
                    ->log('Choix des boutiques actives sous quota mis à jour.');
            }
        }
    }
}
