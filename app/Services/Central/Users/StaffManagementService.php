<?php

namespace App\Services\Central\Users;

use App\DTOs\Central\Users\UserData;
use App\DTOs\Central\Users\UserProfileData;
use App\DTOs\Central\Users\UserSaveResult;
use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class StaffManagementService
{
    public function __construct(
        private UserManagementService $users,
        private AuthorizationMutation $mutations,
    ) {}

    /** @return LengthAwarePaginator<User> */
    public function search(User $actor, string $search, ?StatusEnum $status = null): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAnyStaff', User::class);
        $query = User::query()->whereHas('roles', fn ($query) => $query->where('guard_name', 'central'))
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email', 'status', 'email_verified_at'])
            ->with('roles:id,uuid,label,guard_name');
        if ($status === StatusEnum::DELETED) {
            $query->onlyTrashed();
        }
        if ($status !== null) {
            $query->where('status', $status->value);
        }
        if (trim($search) !== '') {
            $query->where(function ($query) use ($search): void {
                $pattern = '%'.trim($search).'%';
                $query->where('first_name', 'like', $pattern)->orWhere('last_name', 'like', $pattern)->orWhere('email', 'like', $pattern);
            });
        }

        return $query->orderBy('id')->paginate(20);
    }

    public function create(User $actor, UserData $data): UserSaveResult
    {
        abort_unless($data->staff, 422);

        return $this->users->create($actor, $data);
    }

    public function update(User $actor, User $target, UserProfileData $data): UserSaveResult
    {
        return $this->users->update($actor, $target, $data, ability: 'updateStaff');
    }

    public function changeStatus(User $actor, User $target, StatusEnum $status): void
    {
        $this->mutations->run(function () use ($actor, $target, $status): void {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($status === StatusEnum::SUSPENDED ? 'suspendStaff' : 'activateStaff', $target);
            $this->users->changeStatus($actor, $target, $status);
        });
    }

    public function archive(User $actor, User $target): void
    {
        $this->mutations->run(function () use ($actor, $target): void {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('archiveStaff', $target);
            $this->users->archive($actor, $target);
        });
    }
}
