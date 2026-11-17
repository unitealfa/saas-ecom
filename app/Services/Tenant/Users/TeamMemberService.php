<?php

namespace App\Services\Tenant\Users;

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Models\Tenant\Users\User;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\Shop\Shop;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

final class TeamMemberService
{
    public function state(User $member): string
    {
        return hash('sha256', json_encode([$member->status->value, $member->membership_status->value,
            $member->updated_at?->toISOString(), $member->deleted_at?->toISOString()], JSON_THROW_ON_ERROR));
    }

    /** @param array{action:string,expected_state:string,last_name?:string,first_name?:string} $data */
    public function change(User $actor, User $member, array $data): void
    {
        abort_unless(tenancy()->initialized, 403);
        DB::connection('tenant')->transaction(function () use ($actor, $member, $data): void {
            Shop::where('singleton', 1)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
            $member = User::withTrashed()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageMembership', $member);
            if (! hash_equals($this->state($member), $data['expected_state'])) {
                throw ValidationException::withMessages(['expected_state' => 'Le membre a changé. Rechargez.']);
            }
            $action = $data['action'];
            if ($action === 'update') {
                abort_unless($member->status === StatusEnum::ACTIVE && ! $member->trashed(), 409);
                $member->forceFill(['last_name' => $data['last_name'], 'first_name' => $data['first_name'] ?? null])->save();
            } elseif ($action === 'suspend') {
                abort_unless($member->membership_status === MembershipStatusEnum::ACTIVE && ! $member->trashed(), 409);
                $member->forceFill(['membership_status' => MembershipStatusEnum::SUSPENDED,
                    'status' => StatusEnum::SUSPENDED, 'remember_token' => Str::random(60)])->save();
                $this->invalidateSessions($member);
            } elseif ($action === 'archive') {
                abort_unless(! $member->trashed(), 409);
                $member->forceFill(['membership_status' => MembershipStatusEnum::REVOKED,
                    'status' => StatusEnum::DELETED, 'remember_token' => Str::random(60)])->save();
                $member->delete();
                $this->invalidateSessions($member);
            } else {
                abort(422);
            }
            activity()->causedBy($actor)->performedOn($member)->event('tenant_member_'.$action)
                ->log('Membre local modifié');
        }, 3);
    }

    private function invalidateSessions(User $member): void
    {
        DB::connection('tenant')->table('sessions')->where('user_id', $member->id)->delete();
    }
}
