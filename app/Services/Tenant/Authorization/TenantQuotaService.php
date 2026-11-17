<?php

namespace App\Services\Tenant\Authorization;

use App\Enums\Central\Subscriptions\StatusEnum as SubscriptionStatus;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Roles\Role;
use App\Models\Tenant\Users\TeamInvitation;
use App\Models\Tenant\Users\User;
use Illuminate\Validation\ValidationException;

/** Plan limits are read directly from the immutable central subscription version. */
final class TenantQuotaService
{
    public function limit(string $featureCode): ?int
    {
        $tenant = tenancy()->tenant;
        abort_unless(tenancy()->initialized && $tenant instanceof Tenant && $tenant->owner !== null, 403);
        $current = Subscription::where('user_id', $tenant->owner->id)
            ->whereIn('status', [SubscriptionStatus::ACTIVE, SubscriptionStatus::TRIAL])
            ->where('period_starts_at', '<=', now())
            ->orderByDesc('period_starts_at')->first();
        if ($current === null || ($current->effectiveEndsAt() !== null && $current->effectiveEndsAt()->isPast())) {
            throw ValidationException::withMessages(['quota' => 'Abonnement actif et vérifié requis.']);
        }
        $feature = Feature::where('code', $featureCode)->where('is_active', true)->first();
        if ($feature === null) {
            throw ValidationException::withMessages(['quota' => 'Fonctionnalité non disponible dans l’offre.']);
        }
        $entitlement = PlanFeature::where('plan_id', $current->plan_id)->where('feature_id', $feature->id)
            ->where('is_active', true)->first();
        if ($entitlement === null) {
            throw ValidationException::withMessages(['quota' => 'Fonctionnalité non comprise dans l’offre.']);
        }
        return $entitlement->limit === null ? null : (int) $entitlement->limit;
    }

    /** Call only while holding the local shop.singleton=1 lock. */
    public function assertCanCreateCustomRole(): void
    {
        $limit = $this->limit('team.custom_roles');
        $used = Role::where('is_system', false)->where('is_protected', false)->count();
        if ($limit !== null && $used >= $limit) {
            throw ValidationException::withMessages(['quota' => 'Nombre maximum de profils personnalisés atteint.']);
        }
    }

    /** Count members and invitations reserving a NEW seat, without double-counting email. */
    public function assertCanReserveSeat(string $email, bool $accepting = false): void
    {
        $limit = $this->limit('team.members');
        $email = mb_strtolower(trim($email));
        $members = User::whereNull('central_user_uuid')->whereNull('deleted_at')
            ->where('membership_status', '<>', \App\Enums\Tenant\Users\MembershipStatusEnum::REVOKED->value)
            ->pluck('email')->map(fn ($value) => mb_strtolower((string) $value))->unique();
        $pending = TeamInvitation::whereNull('accepted_at')->whereNull('revoked_at')
            ->where('expires_at', '>', now())->pluck('email')
            ->map(fn ($value) => mb_strtolower((string) $value))->unique();
        $seats = $members->merge($pending)->unique();
        $willNeedSeat = ! $seats->contains($email);
        if ($limit !== null && ($seats->count() + ($willNeedSeat ? 1 : 0)) > $limit) {
            throw ValidationException::withMessages(['quota' => 'Quota des membres de boutique atteint.']);
        }
    }
}
