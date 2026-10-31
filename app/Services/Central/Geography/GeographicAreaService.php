<?php

namespace App\Services\Central\Geography;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class GeographicAreaService
{
    private const array TENANT_REFERENCES = [
        'shop_addresses' => ['province_uuid', 'municipality_uuid'],
        'order_revisions' => ['province_uuid', 'municipality_uuid'],
        'shipping_rates' => ['province_uuid', 'municipality_uuid'],
        'free_shipping_rules' => ['province_uuid'],
    ];

    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?GeographicArea $area = null): GeographicArea
    {
        abort_if(tenancy()->initialized, 403);
        try {
            return DB::transaction(function () use ($actor, $data, $area): GeographicArea {
                $country = Country::where($area === null ? 'uuid' : 'id', $area === null ? $data['country_uuid'] : $area->country_id)->lockForUpdate()->first();
                if ($country === null) {
                    throw ValidationException::withMessages(['country_uuid' => 'Ce pays est introuvable.']);
                }
                $target = $area === null ? new GeographicArea : GeographicArea::whereKey($area->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize($target->exists ? 'update' : 'create', $target->exists ? $target : GeographicArea::class);
                if ($target->exists) {
                    $this->assertCurrent($target, $data['expected_state']);
                } else {
                    $type = GeoZoneTypeEnum::from((int) $data['type']);
                    $parent = filled($data['parent_uuid'] ?? null) ? GeographicArea::where('uuid', $data['parent_uuid'])->lockForUpdate()->first() : null;
                    if (($type === GeoZoneTypeEnum::PROVINCE && filled($data['parent_uuid'] ?? null))
                        || ($type === GeoZoneTypeEnum::MUNICIPALITY && ($parent === null || $parent->type !== GeoZoneTypeEnum::PROVINCE || $parent->country_id !== $country->id))) {
                        throw ValidationException::withMessages(['parent_uuid' => 'Une commune exige une wilaya du même pays ; une wilaya ne possède pas de parent.']);
                    }
                    $target->forceFill(['country_id' => $country->id, 'type' => $type, 'parent_id' => $parent?->id, 'is_active' => false, 'code' => $data['code']]);
                }
                $before = $target->exists ? $target->getAttributes() : null;
                $target->fill(array_intersect_key($data, array_flip(['name_fr', 'name_ar', 'reference_source', 'reference_version', 'effective_at'])));
                $target->reference_source = filled($target->reference_source) ? $target->reference_source : 'manual';
                $target->reference_version = filled($target->reference_version) ? $target->reference_version : 'manual';
                $target->save();
                $this->audit($actor, $target, 'geography_saved', $before);

                return $target;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Ce code est déjà utilisé dans ce pays et ce rattachement, y compris par une zone archivée.']);
        }
    }

    public function setActive(User $actor, GeographicArea $area, bool $active, string $expectedState): void
    {
        $this->mutate($actor, $area, 'activate', $expectedState, function (GeographicArea $target) use ($active): void {
            if ($active && ($target->country->trashed() || ! $target->country->is_active || ($target->parent_id !== null && ($target->parent === null || $target->parent->trashed() || ! $target->parent->is_active)))) {
                throw ValidationException::withMessages(['is_active' => 'Le pays et la wilaya parente doivent être actifs.']);
            }
            $target->forceFill(['is_active' => $active])->save();
        });
    }

    public function archive(User $actor, GeographicArea $area, string $expectedState): void
    {
        $this->mutate($actor, $area, 'archive', $expectedState, function (GeographicArea $target): void {
            if ($this->isReferenced($target)) {
                throw ValidationException::withMessages(['area' => 'Cette zone possède des dépendances ou des références historiques. Désactivez-la au lieu de son archivage.']);
            }
            $target->forceFill(['is_active' => false])->save();
            $target->delete();
        });
    }

    /** @param Closure(GeographicArea): void $mutation */
    private function mutate(User $actor, GeographicArea $area, string $ability, string $expectedState, Closure $mutation): void
    {
        abort_if(tenancy()->initialized, 403);
        DB::transaction(function () use ($actor, $area, $ability, $expectedState, $mutation): void {
            Country::whereKey($area->country_id)->lockForUpdate()->firstOrFail();
            $target = GeographicArea::whereKey($area->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($ability, $target);
            $this->assertCurrent($target, $expectedState);
            $before = $target->getAttributes();
            $mutation($target);
            $this->audit($actor, $target, 'geography_'.$ability, $before);
        }, 3);
    }

    private function assertCurrent(GeographicArea $area, string $expectedState): void
    {
        if (! hash_equals($area->stateToken(), $expectedState)) {
            throw ValidationException::withMessages(['conflict' => 'Cette zone a changé. Rechargez la page avant de réessayer.']);
        }
    }

    /** @param array<string, mixed>|null $before */
    private function audit(User $actor, GeographicArea $area, string $event, ?array $before): void
    {
        activity()->causedBy($actor)->performedOn($area)->event($event)->withProperties(['before' => $before, 'after' => $area->getAttributes()])->log('Référentiel géographique modifié.');
    }

    private function isReferenced(GeographicArea $area): bool
    {
        $connection = $area->getConnection();
        if ($area->children()->withTrashed()->exists()
            || $connection->table('carrier_geo_mappings')->where('geographic_area_id', $area->id)->exists()
            || $connection->table('pickup_points')->where('province_id', $area->id)->orWhere('municipality_id', $area->id)->exists()) {
            return true;
        }
        foreach (Tenant::withTrashed()->whereNotNull('provisioned_at')->cursor() as $tenant) {
            try {
                $referenced = $tenant->run(function () use ($area): bool {
                    foreach (self::TENANT_REFERENCES as $table => $columns) {
                        if (DB::connection('tenant')->table($table)->where(function ($references) use ($columns, $area): void {
                            foreach ($columns as $column) {
                                $references->orWhere($column, $area->uuid);
                            }
                        })->exists()) {
                            return true;
                        }
                    }

                    return false;
                });
            } catch (Throwable $exception) {
                report($exception);
                throw ValidationException::withMessages(['area' => 'Les références des boutiques ne peuvent pas être vérifiées actuellement. Archivage refusé.']);
            }
            if ($referenced) {
                return true;
            }
        }

        return false;
    }
}
