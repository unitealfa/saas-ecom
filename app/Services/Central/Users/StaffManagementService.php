<?php

namespace App\Services\Central\Users;

use App\DTOs\Central\Users\UserData;
use App\DTOs\Central\Users\UserProfileData;
use App\DTOs\Central\Users\UserSaveResult;
use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class StaffManagementService
{
    private const int PER_PAGE = 20;

    private const int MIN_PHONE_DIGITS = 5;

    private const string GUARD = 'central';

    private const string ABILITY_VIEW_ANY = 'viewAnyStaff';

    private const string ABILITY_UPDATE = 'updateStaff';

    public function __construct(private UserManagementService $users) {}

    /** @return LengthAwarePaginator<int, User> */
    public function search(User $actor, string $search, ?StatusEnum $status = null): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize(self::ABILITY_VIEW_ANY, User::class);

        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('guard_name', self::GUARD))
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email', 'status', 'email_verified_at'])
            ->with(['roles:id,uuid,label,guard_name', 'profilePhoto:id,uuid,model_type,model_id'])
            ->when($status === StatusEnum::DELETED, fn (Builder $query) => $query->onlyTrashed())
            ->when($status !== null, fn (Builder $query) => $query->withStatus($status))
            ->when(trim($search) !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->orderBy('id')
            ->paginate(self::PER_PAGE);
    }

    public function create(User $actor, UserData $data): UserSaveResult
    {
        abort_unless($data->staff, 422);

        return $this->users->create($actor, $data);
    }

    public function update(User $actor, User $target, UserProfileData $data): UserSaveResult
    {
        return $this->users->update($actor, $target, $data, ability: self::ABILITY_UPDATE);
    }

    public function changeStatus(User $actor, User $target, StatusEnum $status): void
    {
        $this->users->changeStatus($actor, $target, $status, staffOnly: true);
    }

    public function archive(User $actor, User $target): void
    {
        $this->users->archive($actor, $target, staffOnly: true);
    }

    /** @param Builder<User> $query */
    private function applySearch(Builder $query, string $search): void
    {
        $pattern = '%'.trim($search).'%';
        $digits = preg_replace('/[^0-9]/', '', $search);

        $query->where(function (Builder $query) use ($pattern, $digits): void {
            $query->where('first_name', 'like', $pattern)
                ->orWhere('last_name', 'like', $pattern)
                ->orWhere('email', 'like', $pattern)
                ->orWhere('phone', 'like', $pattern);

            if (strlen($digits) >= self::MIN_PHONE_DIGITS) {
                $query->orWhere('phone', 'like', '%'.ltrim($digits, '0').'%');
            }
        });
    }
}
