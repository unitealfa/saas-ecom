<?php

namespace App\Services\Central\Users;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;

class UserApprovalService
{
    public function __construct(private AuthorizationMutation $mutations) {}

    /** @return LengthAwarePaginator<User> */
    public function pending(User $actor): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);

        return User::where('status', StatusEnum::PENDING->value)
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email', 'phone', 'country_id', 'status', 'created_at'])
            ->with(['country:id,name_fr', 'roles:id,guard_name,is_super_admin'])
            ->orderBy('created_at')->orderBy('id')->paginate(20);
    }

    public function decide(User $actor, User $target, bool $approved, ?string $reason = null): void
    {
        $this->mutations->run(function () use ($actor, $target, $approved, $reason): void {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($approved ? 'approve' : 'reject', $target);
            $target->status = $approved ? StatusEnum::ACTIVE : StatusEnum::REJECTED;
            if (! $approved) {
                $target->remember_token = null;
                Password::broker('central')->deleteToken($target);
                $target->getConnection()->table('sessions')->where('user_id', $target->id)->delete();
            }
            $target->save();
            activity()->causedBy($actor)->performedOn($target)
                ->event($approved ? 'central_account_approved' : 'central_account_rejected')
                ->withProperties(['previous_status' => StatusEnum::PENDING->value, 'status' => $target->status->value, 'reason' => $reason])
                ->log($approved ? 'Inscription centrale approuvée.' : 'Demande d’accès centrale refusée.');
        });
    }
}
