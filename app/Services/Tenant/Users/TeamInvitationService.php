<?php

namespace App\Services\Tenant\Users;

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Models\Tenant\Roles\Role;
use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Users\TeamInvitation;
use App\Models\Tenant\Users\User;
use App\Notifications\Tenant\TeamInvitationNotification;
use App\Notifications\Tenant\WelcomeEmployeeNotification;
use App\Services\Tenant\Authorization\TenantAccess;
use App\Services\Tenant\Authorization\TenantQuotaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TeamInvitationService
{
    public function __construct(private readonly TenantInvitationUrl $urls, private readonly TenantQuotaService $quotas) {}

    public function state(TeamInvitation $invitation): string
    {
        return hash('sha256', implode('|', [$invitation->uuid, $invitation->updated_at?->toISOString(),
            $invitation->role_permission_version, $invitation->accepted_at?->toISOString(),
            $invitation->revoked_at?->toISOString()]));
    }

    public function send(User $actor, string $email, Role $role): TeamInvitation
    {
        abort_unless(tenancy()->initialized, 403);
        abort_unless(config('mail.default') !== 'log', 503, 'Configurer un transport e-mail non journalisé.');
        $secret = bin2hex(random_bytes(32));
        $email = mb_strtolower(trim($email));
        // Check the primary trusted domain before any write.
        $this->urls->make('tenant.login');
        $invitation = DB::connection('tenant')->transaction(function () use ($actor, $email, $role, $secret): TeamInvitation {
            Shop::where('singleton', 1)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', TeamInvitation::class);
            Gate::forUser($actor)->authorize('assign', $role);
            foreach ($role->permissionComposition()->get() as $permission) {
                abort_unless($actor->can($permission->name), 403);
            }
            $existing = User::withTrashed()->where('email', $email)->first();
            if (mb_strtolower($actor->email) === $email
                || ($existing !== null && (! TenantAccess::editableEmployee($existing) || ! TenantAccess::active($existing)))) {
                throw ValidationException::withMessages(['email' => 'Invitation impossible pour ce compte.']);
            }
            $this->quotas->assertCanReserveSeat($email);
            if (TeamInvitation::where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')
                ->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['email' => 'Invitation en cours pour cette adresse.']);
            }
            $invite = new TeamInvitation;
            $invite->forceFill([
                'initial_role_id' => $role->id, 'invited_by_id' => $actor->id,
                'email' => $email, 'token_hash' => hash('sha256', $secret),
                'role_permission_version' => $role->permission_version,
                'expires_at' => now()->addHours((int) config('tenant_team.invitation_ttl_hours', 48)),
            ])->save();
            activity()->causedBy($actor)->performedOn($invite)->event('team_invitation_created')
                ->withProperties(['role_uuid' => $role->uuid])->log('Invitation créée');
            return $invite;
        }, 3);
        $url = $this->urls->make('tenant.invitations.show', ['invitation' => $invitation->uuid, 'token' => $secret]);
        $shopName = Shop::where('singleton', 1)->value('shop_name');
        Notification::route('mail', $email)->notify(new TeamInvitationNotification($url, $shopName));
        return $invitation;
    }

    public function revoke(User $actor, TeamInvitation $invite, string $expected): void
    {
        DB::connection('tenant')->transaction(function () use ($actor, $invite, $expected): void {
            Shop::where('singleton', 1)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $invite = TeamInvitation::whereKey($invite->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('revoke', $invite);
            if (! hash_equals($this->state($invite), $expected)) {
                throw ValidationException::withMessages(['expected_state' => 'Invitation modifiée. Rechargez.']);
            }
            $invite->forceFill(['revoked_at' => now()])->save();
            activity()->causedBy($actor)->performedOn($invite)->event('team_invitation_revoked')->log('Invitation révoquée');
        }, 3);
    }

    public function resend(User $actor, TeamInvitation $invite, string $expected): TeamInvitation
    {
        abort_unless(config('mail.default') !== 'log', 503, 'Configurer un transport e-mail non journalisé.');
        $secret = bin2hex(random_bytes(32));
        $this->urls->make('tenant.login');
        $new = DB::connection('tenant')->transaction(function () use ($actor, $invite, $expected, $secret): TeamInvitation {
            Shop::where('singleton', 1)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $current = TeamInvitation::whereKey($invite->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('resend', $current);
            if (! hash_equals($this->state($current), $expected)) {
                throw ValidationException::withMessages(['expected_state' => 'Invitation modifiée. Rechargez.']);
            }
            $role = Role::whereKey($current->initial_role_id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('assign', $role);
            foreach ($role->permissionComposition()->get() as $permission) {
                abort_unless($actor->can($permission->name), 403);
            }
            $this->quotas->assertCanReserveSeat($current->email);
            $current->forceFill(['revoked_at' => now()])->save();
            $replacement = new TeamInvitation;
            $replacement->forceFill([
                'initial_role_id' => $role->id, 'invited_by_id' => $actor->id,
                'email' => $current->email, 'token_hash' => hash('sha256', $secret),
                'role_permission_version' => $role->permission_version,
                'expires_at' => now()->addHours((int) config('tenant_team.invitation_ttl_hours', 48)),
            ])->save();
            activity()->causedBy($actor)->performedOn($current)->event('team_invitation_resent')
                ->withProperties(['replacement_uuid' => $replacement->uuid])->log('Invitation remplacée');
            return $replacement;
        }, 3);
        $url = $this->urls->make('tenant.invitations.show', ['invitation' => $new->uuid, 'token' => $secret]);
        Notification::route('mail', $new->email)->notify(new TeamInvitationNotification($url,
            Shop::where('singleton', 1)->value('shop_name')));
        return $new;
    }

    public function valid(TeamInvitation $invite, string $secret): bool
    {
        return strlen($secret) === 64 && ctype_xdigit($secret)
            && hash_equals($invite->token_hash, hash('sha256', $secret))
            && $invite->accepted_at === null && $invite->revoked_at === null
            && $invite->expires_at->isFuture();
    }

    public function accept(TeamInvitation $invite, string $secret, ?string $password, ?string $lastName, ?string $firstName): User
    {
        abort_unless(tenancy()->initialized, 403);
        $member = DB::connection('tenant')->transaction(function () use ($invite, $secret, $password, $lastName, $firstName): User {
            Shop::where('singleton', 1)->lockForUpdate()->firstOrFail();
            $invite = TeamInvitation::whereKey($invite->id)->lockForUpdate()->firstOrFail();
            if (! $this->valid($invite, $secret)) {
                throw ValidationException::withMessages(['invitation' => 'Invitation invalide ou expirée.']);
            }
            $role = Role::whereKey($invite->initial_role_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $role->permission_version === (int) $invite->role_permission_version, 409);
            $inviter = User::whereKey($invite->invited_by_id)->lockForUpdate()->firstOrFail();
            Gate::forUser($inviter)->authorize('create', TeamInvitation::class);
            Gate::forUser($inviter)->authorize('assign', $role);
            foreach ($role->permissionComposition()->get() as $permission) {
                abort_unless($inviter->can($permission->name), 403);
            }
            $this->quotas->assertCanReserveSeat($invite->email, accepting: true);
            $member = User::withTrashed()->where('email', $invite->email)->lockForUpdate()->first();
            $sessionUser = Auth::guard('tenant')->user();
            if ($member === null) {
                if ($sessionUser !== null || $password === null) {
                    throw ValidationException::withMessages(['invitation' => 'Activez le compte depuis une session déconnectée.']);
                }
                $member = new User(['email' => $invite->email, 'last_name' => $lastName, 'first_name' => $firstName,
                    'password' => $password]);
                $member->forceFill(['status' => StatusEnum::ACTIVE, 'membership_status' => MembershipStatusEnum::ACTIVE,
                    'joined_at' => now(), 'email_verified_at' => now()])->save();
            } else {
                abort_unless(TenantAccess::editableEmployee($member) && TenantAccess::active($member)
                    && $sessionUser instanceof User && $sessionUser->id === $member->id, 403);
            }
            $member->assignRole($role);
            $invite->forceFill(['accepted_at' => now()])->save();
            activity()->causedBy($member)->performedOn($invite)->event('team_invitation_accepted')
                ->log('Invitation acceptée');
            return $member;
        }, 3);
        $url = $this->urls->make('tenant.login');
        $shopName = Shop::where('singleton', 1)->value('shop_name');
        $member->notify(new WelcomeEmployeeNotification($shopName, $url));
        return $member;
    }
}
