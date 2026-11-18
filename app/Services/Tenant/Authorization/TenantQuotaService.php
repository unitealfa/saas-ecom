<?php

namespace App\Services\Tenant\Authorization;

use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Enums\Central\Subscriptions\StatusEnum as SubscriptionStatus;
use App\Enums\Tenant\Users\MembershipStatusEnum;
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
            ->currentlyEffective()->orderByDesc('id')->first();
        if ($current === null) {
            throw ValidationException::withMessages(['quota' => 'Abonnement actif et vérifié requis.']);
        }
        $feature = Feature::where('code', $featureCode)->where('is_active', true)->first();
        if ($feature === null) {
            throw ValidationException::withMessages(['quota' => 'Fonctionnalité non disponible dans l’offre.']);
        }
        // Ces deux quotas commerciaux comptent une occupation par base boutique,
        // et jamais des permissions Spatie individuelles.
        if (in_array($featureCode, [Feature::TEAM_CUSTOM_ROLES_CODE, Feature::TEAM_MEMBERS_CODE], true)
            && ($feature->value_type !== ValueTypeEnum::QUANTITY
                || $feature->quota_scope !== QuotaScopeEnum::TENANT
                || $feature->period !== PeriodEnum::LIFETIME)) {
            throw ValidationException::withMessages([
                'quota' => 'Le quota commercial d’équipe présente une portée ou un type incohérent.',
            ]);
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
            throw ValidationException::withMessages(['quota' => "Limite de l’offre atteinte : {$limit} profil(s) personnalisé(s) maximum."]);
        }
    }

    /** Count members and invitations reserving a NEW seat, without double-counting email. */
    public function assertCanReserveSeat(string $email, bool $accepting = false): void
    {
        $limit = $this->limit('team.members');
        $email = mb_strtolower(trim($email));
        $members = User::whereNull('central_user_uuid')->whereNull('deleted_at')
            ->where('membership_status', '<>', MembershipStatusEnum::REVOKED->value)
            ->pluck('email')->map(fn ($value) => mb_strtolower((string) $value))->unique();
        $pending = TeamInvitation::whereNull('accepted_at')->whereNull('revoked_at')
            ->where('expires_at', '>', now())->pluck('email')
            ->map(fn ($value) => mb_strtolower((string) $value))->unique();
        $seats = $members->merge($pending)->unique();
        $willNeedSeat = ! $seats->contains($email);
        if ($limit !== null && ($seats->count() + ($willNeedSeat ? 1 : 0)) > $limit) {
            throw ValidationException::withMessages(['quota' => "Limite de l’offre atteinte : {$limit} membre(s) maximum."]);
        }
    }
}
