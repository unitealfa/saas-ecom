<?php

namespace App\Services\Central\Plans;

use App\Models\Central\Features\Feature;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FeatureCatalogService
{
    private const int PER_PAGE = 20;

    private const array EDITABLE = ['code', 'name', 'value_type', 'unit', 'quota_scope', 'period'];

    private const array TECHNICAL = ['value_type', 'unit', 'quota_scope', 'period'];

    private const array AUDITED = [...self::EDITABLE, 'is_active', 'deleted_at'];

    private const string EVENT_CREATED = 'feature_created';

    private const string EVENT_UPDATED = 'feature_updated';

    private const string EVENT_ACTIVATED = 'feature_activation_updated';

    private const string EVENT_ARCHIVED = 'feature_archived';

    public function __construct(private readonly AuthorizationMutation $mutations) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Feature>
     */
    public function search(User $actor, array $filters): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Feature::class);

        $search = trim((string) ($filters['search'] ?? ''));
        $pattern = '%'.addcslashes($search, '\\%_').'%';

        return Feature::withCount('planFeatures')
            ->when($filters['archived'] ?? false, fn (Builder $query) => $query->onlyTrashed())
            ->when(isset($filters['is_active']), fn (Builder $query) => $query->where('is_active', (bool) $filters['is_active']))
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('name', 'like', $pattern)->orWhere('code', 'like', $pattern),
            ))
            ->orderBy('name')->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /** @return array{plans: int, usages: int, permissions: int} */
    public function references(User $actor, Feature $feature): array
    {
        Gate::forUser($actor)->authorize('view', $feature);

        return [
            'plans' => $feature->planFeatures()->count(),
            'usages' => $this->usages($feature)->count(),
            'permissions' => $this->permissions($feature)->count(),
        ];
    }

    public function configurationLocked(User $actor, Feature $feature): bool
    {
        Gate::forUser($actor)->authorize('update', $feature);

        return $feature->isSystem() || $this->isReferenced($feature);
    }

    /** @param array{code: string, name: string, value_type: int|string, unit?: ?string, quota_scope: int|string, period: int|string} $data */
    public function create(User $actor, array $data): Feature
    {
        return $this->mutations->run($actor, 'create', fn () => Feature::class,
            function () use ($actor, $data): Feature {
                $feature = new Feature(Arr::only($data, self::EDITABLE));

                if ($feature->isSystem()) {
                    $this->fail('code', 'Ce code est réservé au référentiel système.');
                }

                $this->save($feature);
                $this->audit($actor, $feature, self::EVENT_CREATED, null);

                return $feature->refresh();
            });
    }

    /** @param array{code: string, name: string, value_type: int|string, unit?: ?string, quota_scope: int|string, period: int|string} $data */
    public function update(User $actor, Feature $feature, array $data, string $expected): void
    {
        $this->mutations->run($actor, 'update', fn () => $this->lock($feature, $expected),
            function (Feature $feature) use ($actor, $data): void {
                $before = $this->snapshot($feature);
                $feature->fill(Arr::only($data, self::EDITABLE));

                if ($feature->isDirty('code')) {
                    $this->fail('code', 'Le code technique est stable et ne peut plus être modifié.');
                }

                if ($feature->isDirty(self::TECHNICAL) && ($feature->isSystem() || $this->isReferenced($feature))) {
                    $this->fail('feature', 'Les paramètres techniques de cette fonctionnalité sont utilisés. Seul son nom peut être modifié.');
                }

                if (! $feature->isDirty()) {
                    return;
                }

                $this->save($feature);
                $this->audit($actor, $feature, self::EVENT_UPDATED, $before);
            });
    }

    public function setActive(User $actor, Feature $feature, bool $active, string $expected): void
    {
        $this->mutations->run($actor, 'activate', fn () => $this->lock($feature, $expected),
            function (Feature $feature) use ($actor, $active): void {
                if (! $active && $feature->planFeatures()->exists()) {
                    $this->fail('feature', 'Cette fonctionnalité appartient à une offre. Sa désactivation est bloquée pour préserver les abonnements.');
                }

                if ($feature->is_active === $active) {
                    return;
                }

                $before = $this->snapshot($feature);
                $feature->forceFill(['is_active' => $active])->save();
                $this->audit($actor, $feature, self::EVENT_ACTIVATED, $before);
            });
    }

    public function archive(User $actor, Feature $feature, string $expected): void
    {
        $this->mutations->run($actor, 'archive', fn () => $this->lock($feature, $expected),
            function (Feature $feature) use ($actor): void {
                if ($this->isReferenced($feature)) {
                    $this->fail('feature', 'Cette fonctionnalité est utilisée par une offre, un compteur ou une référence de permission. Elle ne peut pas être archivée.');
                }

                $before = $this->snapshot($feature);
                $feature->forceFill(['is_active' => false])->save();
                $feature->delete();
                $this->audit($actor, $feature, self::EVENT_ARCHIVED, $before);
            });
    }

    /** Verrouille la ligne et refuse la modification si l'état a changé depuis l'affichage du formulaire. */
    private function lock(Feature $feature, string $expected): Feature
    {
        $current = Feature::whereKey($feature->id)->lockForUpdate()->firstOrFail();

        if (! hash_equals($current->managementState(), $expected)) {
            $this->fail('feature', 'Cette fonctionnalité a changé. Rechargez le formulaire avant de recommencer.');
        }

        return $current;
    }

    private function isReferenced(Feature $feature): bool
    {
        return $feature->planFeatures()->exists()
            || $this->usages($feature)->exists()
            || $this->permissions($feature)->exists();
    }

    private function usages(Feature $feature): QueryBuilder
    {
        return $feature->getConnection()->table('feature_usage')->where('feature_id', $feature->id);
    }

    /** @return Builder<Permission> */
    private function permissions(Feature $feature): Builder
    {
        return Permission::where('feature_code', $feature->code);
    }

    private function save(Feature $feature): void
    {
        try {
            $feature->save();
        } catch (UniqueConstraintViolationException) {
            $this->fail('code', 'Ce code est déjà réservé, y compris pour une fonctionnalité archivée.');
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Feature $feature): array
    {
        return $feature->only(self::AUDITED);
    }

    /** @param array<string, mixed>|null $before */
    private function audit(User $actor, Feature $feature, string $event, ?array $before): void
    {
        activity()->causedBy($actor)->performedOn($feature)->event($event)
            ->withProperties(['before' => $before, 'after' => $this->snapshot($feature)])
            ->log('Catalogue des fonctionnalités commerciales modifié.');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}