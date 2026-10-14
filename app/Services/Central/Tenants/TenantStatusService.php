<?php

namespace App\Services\Central\Tenants;

use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Enums\Central\Tenants\StatusEnum;
use App\Enums\Central\Users\StatusEnum as UserStatus;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Feature;
use App\Models\Central\PlanFeature;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Subscriptions\DefaultSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TenantStatusService
{
    public function __construct(
        private AuthorizationMutation $mutations,
        private DefaultSubscription $subscriptions,
        private TenantProfileProjection $profiles,
        private CentralUserAccess $access,
    ) {}

    public function change(User $actor, Tenant $tenant, string $action, StatusEnum $expected, ?string $reason): void
    {
        abort_unless(in_array($action, ['suspend', 'activate', 'archive'], true), 403);
        $this->mutations->run(function () use ($actor, $tenant, $action, $expected, $reason): void {
            $owner = User::withTrashed()->whereKey($tenant->user_id)->lockForUpdate()->firstOrFail();
            $shops = Tenant::where('user_id', $owner->id)->orderBy('id')->lockForUpdate()->get();
            $tenant = $shops->first(fn (Tenant $shop): bool => $shop->id === $tenant->id && $shop->uuid === $tenant->uuid);
            abort_if($tenant === null, 404);
            Gate::forUser($actor)->authorize($action, $tenant);
            if ($tenant->status !== $expected) {
                throw ValidationException::withMessages(['tenant' => 'L’état a changé. Rechargez la boutique avant de continuer.']);
            }
            $deployments = TenantSchemaDeployment::where('tenant_id', $tenant->id)
                ->whereIn('status', [DeploymentStatus::PENDING, DeploymentStatus::RUNNING])->orderBy('id')->lockForUpdate()->get();
            if ($deployments->contains('status', DeploymentStatus::RUNNING)) {
                throw ValidationException::withMessages(['tenant' => 'Une opération technique est en cours. Attendez sa fin.']);
            }
            $before = $tenant->status;
            if ($action === 'archive') {
                foreach ($deployments as $deployment) {
                    $deployment->update(['status' => DeploymentStatus::CANCELLED, 'ended_at' => now()]);
                }
                $tenant->forceFill(['status' => StatusEnum::DELETED, 'deleted_at' => now(), 'over_quota_since_at' => null])->save();
            } elseif ($action === 'suspend') {
                if ($deployments->isNotEmpty() || $tenant->schema_version === null || $tenant->provisioned_at === null
                    || in_array($tenant->status, [StatusEnum::PROVISIONING_FAILED, StatusEnum::DELETED], true)) {
                    throw ValidationException::withMessages(['tenant' => 'Cette boutique n’est pas encore préparée.']);
                }
                if ($tenant->status === StatusEnum::SUSPENDED) {
                    return;
                }
                $tenant->forceFill(['status' => StatusEnum::SUSPENDED, 'over_quota_since_at' => null])->save();
            } else {
                if ($deployments->isNotEmpty() || $tenant->status === StatusEnum::PROVISIONING_FAILED) {
                    throw ValidationException::withMessages(['tenant' => 'Terminez la préparation technique avant l’activation.']);
                }
                $this->assertOwnerEligible($owner);
                $this->profiles->assertPrepared($tenant, $owner);
                $tenant->status = StatusEnum::ACTIVE;
                $this->rebalance($actor, $owner, $shops, $tenant, $reason);
            }
            activity()->causedBy($actor)->performedOn($tenant)->event('tenant_'.$action)
                ->withProperties(['before' => ['status' => $before->value], 'after' => ['status' => $tenant->status->value], 'reason' => $reason])
                ->log('État administratif de la boutique modifié.');
        });
    }

    /** @param array<string, int|string> $priorities UUID => priorité */
    public function priorities(User $actor, Tenant $tenant, array $priorities, ?string $reason): void
    {
        $this->mutations->run(function () use ($actor, $tenant, $priorities, $reason): void {
            $owner = User::withTrashed()->whereKey($tenant->user_id)->lockForUpdate()->firstOrFail();
            $shops = Tenant::where('user_id', $owner->id)->orderBy('id')->lockForUpdate()->get();
            $tenant = $shops->first(fn (Tenant $shop): bool => $shop->id === $tenant->id && $shop->uuid === $tenant->uuid);
            abort_if($tenant === null, 404);
            Gate::forUser($actor)->authorize('updatePriorities', $tenant);
            $expected = $shops->pluck('uuid')->sort()->values()->all();
            $provided = array_keys($priorities);
            sort($provided);
            $values = array_map(intval(...), array_values($priorities));
            $ordered = $values;
            sort($ordered);
            if ($provided !== $expected || $ordered !== range(1, $shops->count())) {
                throw ValidationException::withMessages(['priorities' => 'Classez toutes les boutiques de ce propriétaire une seule fois, de 1 au nombre de boutiques.']);
            }
            $this->assertOwnerEligible($owner);
            $before = $shops->mapWithKeys(fn (Tenant $shop): array => [$shop->uuid => $shop->activation_priority])->all();
            foreach ($shops as $shop) {
                $shop->activation_priority = (int) $priorities[$shop->uuid];
                $shop->save();
            }
            $this->rebalance($actor, $owner, $shops, null, $reason);
            activity()->causedBy($actor)->performedOn($tenant)->event('tenant_priorities_updated')
                ->withProperties(['before' => $before, 'after' => array_map(intval(...), $priorities), 'reason' => $reason])
                ->log('Priorités des boutiques du propriétaire modifiées.');
        });
    }

    private function assertOwnerEligible(User $owner): void
    {
        if ($owner->trashed() || $owner->status !== UserStatus::ACTIVE || $this->access->isStaff($owner)
            || $owner->email_verified_at === null || $owner->legal_verification_status !== VerificationStatusEnum::VERIFIED
            || $owner->legal_verified_at === null || $owner->legal_profile_version === null) {
            throw ValidationException::withMessages(['tenant' => 'Le propriétaire doit être un commerçant actif avec son e-mail et son dossier professionnel vérifiés.']);
        }
    }

    /** @param Collection<int, Tenant> $shops */
    private function rebalance(User $actor, User $owner, Collection $shops, ?Tenant $requested, ?string $reason): void
    {
        $subscription = $this->subscriptions->ensure($owner, $actor);
        $feature = Feature::where('code', 'shops')->where('is_active', true)->firstOrFail();
        $grant = PlanFeature::where('plan_id', $subscription->plan_id)->where('feature_id', $feature->id)->lockForUpdate()->first();
        if ($grant === null || ! $grant->is_active) {
            throw ValidationException::withMessages(['tenant' => 'L’offre applicable ne permet pas d’activer une boutique.']);
        }
        $busy = TenantSchemaDeployment::whereIn('tenant_id', $shops->pluck('id'))
            ->whereIn('status', [DeploymentStatus::PENDING, DeploymentStatus::RUNNING])->lockForUpdate()->pluck('tenant_id');
        $eligible = $shops->filter(fn (Tenant $shop): bool => in_array($shop->status, [StatusEnum::ACTIVE, StatusEnum::OVER_QUOTA], true)
            && $shop->schema_version !== null && $shop->provisioned_at !== null && ! $busy->contains($shop->id))
            ->sortBy(fn (Tenant $shop): array => [$shop->activation_priority === null ? 1 : 0, $shop->activation_priority ?? PHP_INT_MAX,
                $shop->is_primary ? 0 : 1, $shop->created_at->timestamp, $shop->uuid])->values();
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
