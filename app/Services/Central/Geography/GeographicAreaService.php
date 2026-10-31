<?php

namespace App\Services\Central\Geography;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class GeographicAreaService
{
    private const int ATTEMPTS = 3;

    private const string DEFAULT_REFERENCE = 'manual';

    private const array EDITABLE = ['name_fr', 'name_ar', 'reference_source', 'reference_version', 'effective_at'];

    /** Tables des boutiques qui référencent une zone par son uuid. */
    private const array TENANT_REFERENCES = [
        'shop_addresses' => ['province_uuid', 'municipality_uuid'],
        'order_revisions' => ['province_uuid', 'municipality_uuid'],
        'shipping_rates' => ['province_uuid', 'municipality_uuid'],
        'free_shipping_rules' => ['province_uuid'],
    ];

    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?GeographicArea $area = null): GeographicArea
    {
        $this->assertCentralContext();

        try {
            return DB::transaction(
                fn (): GeographicArea => $area === null ? $this->create($actor, $data) : $this->update($actor, $area, $data),
                self::ATTEMPTS,
            );
        } catch (UniqueConstraintViolationException) {
            $this->fail('code', 'Ce code est déjà utilisé dans ce pays et ce rattachement, y compris par une zone archivée.');
        }
    }

    public function setActive(User $actor, GeographicArea $area, bool $active, string $expectedState): void
    {
        $this->mutate($actor, $area, 'activate', $expectedState, function (GeographicArea $target) use ($active): void {
            if ($active && ! $this->hasActiveAncestors($target)) {
                $this->fail('is_active', 'Le pays et la wilaya parente doivent être actifs.');
            }

            $target->forceFill(['is_active' => $active])->save();
        });
    }

    public function archive(User $actor, GeographicArea $area, string $expectedState): void
    {
        $this->mutate($actor, $area, 'archive', $expectedState, function (GeographicArea $target): void {
            if ($this->isReferenced($target)) {
                $this->fail('area', 'Cette zone possède des dépendances ou des références historiques. Désactivez-la au lieu de son archivage.');
            }

            $target->forceFill(['is_active' => false])->save();
            $target->delete();
        });
    }

    /** @param array<string, mixed> $data */
    private function create(User $actor, array $data): GeographicArea
    {
        $country = $this->lockCountry(Country::where('uuid', $data['country_uuid']));
        Gate::forUser($actor)->authorize('create', GeographicArea::class);

        $type = GeoZoneTypeEnum::from((int) $data['type']);
        $parent = $this->parentFor($type, $country, $data['parent_uuid'] ?? null);

        $area = new GeographicArea;
        $area->forceFill([
            'country_id' => $country->id,
            'type' => $type,
            'parent_id' => $parent?->id,
            'is_active' => false,
            'code' => $data['code'],
        ]);

        return $this->persist($actor, $area, $data, null);
    }

    /** @param array<string, mixed> $data */
    private function update(User $actor, GeographicArea $area, array $data): GeographicArea
    {
        $this->lockCountry(Country::whereKey($area->country_id));

        $target = GeographicArea::whereKey($area->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor)->authorize('update', $target);
        $this->assertCurrent($target, $data['expected_state']);

        return $this->persist($actor, $target, $data, $target->getAttributes());
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $before
     */
    private function persist(User $actor, GeographicArea $area, array $data, ?array $before): GeographicArea
    {
        $area->fill(Arr::only($data, self::EDITABLE));

        foreach (['reference_source', 'reference_version'] as $field) {
            if (blank($area->{$field})) {
                $area->{$field} = self::DEFAULT_REFERENCE;
            }
        }

        $area->save();
        $this->audit($actor, $area, 'geography_saved', $before);

        return $area;
    }

    /** @param Builder<Country> $query */
    private function lockCountry(Builder $query): Country
    {
        return $query->lockForUpdate()->first()
            ?? $this->fail('country_uuid', 'Ce pays est introuvable.');
    }

    /** Une wilaya n'a pas de parent ; une commune exige une wilaya du même pays. */
    private function parentFor(GeoZoneTypeEnum $type, Country $country, ?string $parentUuid): ?GeographicArea
    {
        $parent = filled($parentUuid) ? GeographicArea::where('uuid', $parentUuid)->lockForUpdate()->first() : null;

        $valid = $type === GeoZoneTypeEnum::PROVINCE
            ? ! filled($parentUuid)
            : $parent !== null && $parent->type === GeoZoneTypeEnum::PROVINCE && $parent->country_id === $country->id;

        return $valid
            ? $parent
            : $this->fail('parent_uuid', 'Une commune exige une wilaya du même pays ; une wilaya ne possède pas de parent.');
    }

    /** Le pays et la wilaya parente (si elle existe) doivent être actifs et non archivés. */
    private function hasActiveAncestors(GeographicArea $area): bool
    {
        if ($area->country->trashed() || ! $area->country->is_active) {
            return false;
        }

        return $area->parent_id === null
            || ($area->parent !== null && ! $area->parent->trashed() && $area->parent->is_active);
    }

    /** @param Closure(GeographicArea): void $mutation */
    private function mutate(User $actor, GeographicArea $area, string $ability, string $expectedState, Closure $mutation): void
    {
        $this->assertCentralContext();

        DB::transaction(function () use ($actor, $area, $ability, $expectedState, $mutation): void {
            Country::whereKey($area->country_id)->lockForUpdate()->firstOrFail();
            $target = GeographicArea::whereKey($area->id)->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize($ability, $target);
            $this->assertCurrent($target, $expectedState);

            $before = $target->getAttributes();
            $mutation($target);
            $this->audit($actor, $target, 'geography_'.$ability, $before);
        }, self::ATTEMPTS);
    }

    private function assertCurrent(GeographicArea $area, string $expectedState): void
    {
        if (! hash_equals($area->stateToken(), $expectedState)) {
            $this->fail('conflict', 'Cette zone a changé. Rechargez la page avant de réessayer.');
        }
    }

    private function assertCentralContext(): void
    {
        abort_if(tenancy()->initialized, 403);
    }

    /** @param array<string, mixed>|null $before */
    private function audit(User $actor, GeographicArea $area, string $event, ?array $before): void
    {
        activity()->causedBy($actor)->performedOn($area)->event($event)
            ->withProperties(['before' => $before, 'after' => $area->getAttributes()])
            ->log('Référentiel géographique modifié.');
    }

    private function isReferenced(GeographicArea $area): bool
    {
        return $this->hasCentralReferences($area) || $this->hasTenantReferences($area);
    }

    private function hasCentralReferences(GeographicArea $area): bool
    {
        $connection = $area->getConnection();

        return $area->children()->withTrashed()->exists()
            || $connection->table('carrier_geo_mappings')->where('geographic_area_id', $area->id)->exists()
            || $connection->table('pickup_points')->where('province_id', $area->id)->orWhere('municipality_id', $area->id)->exists();
    }

    /** Parcourt toutes les boutiques préparées : si l'une est illisible, l'archivage est refusé par prudence. */
    private function hasTenantReferences(GeographicArea $area): bool
    {
        foreach (Tenant::withTrashed()->whereNotNull('provisioned_at')->cursor() as $tenant) {
            try {
                tenancy()->initialize($tenant);
                $referenced = $this->isReferencedInTenant($area);
            } catch (Throwable $exception) {
                report($exception);

                $this->fail('area', 'Les références des boutiques ne peuvent pas être vérifiées actuellement. Archivage refusé.');
            } finally {
                tenancy()->end();
            }

            if ($referenced) {
                return true;
            }
        }

        return false;
    }

    private function isReferencedInTenant(GeographicArea $area): bool
    {
        foreach (self::TENANT_REFERENCES as $table => $columns) {
            $found = DB::connection('tenant')->table($table)
                ->where(function ($references) use ($columns, $area): void {
                    foreach ($columns as $column) {
                        $references->orWhere($column, $area->uuid);
                    }
                })
                ->exists();

            if ($found) {
                return true;
            }
        }

        return false;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
