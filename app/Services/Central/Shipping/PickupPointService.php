<?php

namespace App\Services\Central\Shipping;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Shipping\PickupPoint;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Users\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PickupPointService
{
    private const int PER_PAGE = 20;

    private const int ATTEMPTS = 3;

    private const string EVENT_CREATED = 'pickup_point_created';

    private const string EVENT_UPDATED = 'pickup_point_updated';

    private const string EVENT_ACTIVATED = 'pickup_point_activation_updated';

    private const string EVENT_ARCHIVED = 'pickup_point_archived';

    /** @return array<string, mixed> */
    public static function dataRules(): array
    {
        return [
            'carrier_uuid' => ['required', 'uuid'],
            'province_uuid' => ['required', 'uuid'],
            'municipality_uuid' => ['nullable', 'uuid'],
            'external_code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:255'],
            'map_url' => ['nullable', 'url:http,https', 'max:255'],
            'reference_source' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PickupPoint>
     */
    public function search(User $actor, array $filters): LengthAwarePaginator
    {
        $this->authorize($actor, 'viewAny');

        $search = trim((string) ($filters['search'] ?? ''));
        $pattern = '%'.addcslashes($search, '\\%_').'%';

        return PickupPoint::with(['carrier', 'province', 'municipality'])
            ->when($filters['archived'] ?? false, fn (Builder $query) => $query->onlyTrashed())
            ->when($filters['carrier_uuid'] ?? null, fn (Builder $query, string $uuid) => $query->whereRelation('carrier', 'uuid', $uuid))
            ->when(isset($filters['is_carrier_active']), fn (Builder $query) => $query->where('is_carrier_active', (bool) $filters['is_carrier_active']))
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('name', 'like', $pattern)->orWhere('external_code', 'like', $pattern),
            ))
            ->orderBy('name')->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?PickupPoint $point = null, ?string $expected = null): PickupPoint
    {
        $ability = $point === null ? 'create' : 'update';
        $this->authorize($actor, $ability, $point);
        $data = $this->validated($data);

        return DB::transaction(function () use ($actor, $ability, $data, $point, $expected): PickupPoint {
            $carrier = $this->lockCarrier($data['carrier_uuid']);
            $areas = $this->lockAreas([$data]);
            $current = $point === null ? null : $this->lockPoint($point);

            $this->authorize($actor, $ability, $current);

            if ($current !== null) {
                $this->assertCurrent($current, $expected);
            }

            return $this->write($actor, $carrier, $areas, $data, $current, false);
        }, self::ATTEMPTS);
    }

    public function setActive(User $actor, PickupPoint $point, bool $active, string $expected): void
    {
        $this->mutate($actor, $point, 'activate', $expected, function (PickupPoint $current) use ($actor, $active): void {
            if ($current->is_carrier_active === $active) {
                return;
            }

            $before = $this->revise($current, ['is_carrier_active' => $active]);
            $this->audit($actor, $current, self::EVENT_ACTIVATED, $before);
        });
    }

    public function archive(User $actor, PickupPoint $point, string $expected): void
    {
        $this->mutate($actor, $point, 'archive', $expected, function (PickupPoint $current) use ($actor): void {
            $before = $this->revise($current, ['is_carrier_active' => false]);
            $current->delete();
            $this->audit($actor, $current, self::EVENT_ARCHIVED, $before);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, unchanged: int}
     */
    public function import(User $actor, string $carrierUuid, array $rows): array
    {
        $this->authorize($actor, 'import');

        return DB::transaction(function () use ($actor, $carrierUuid, $rows): array {
            $carrier = $this->lockCarrier($carrierUuid);
            $this->authorize($actor, 'import');

            $areas = $this->lockAreas($rows);
            $existing = PickupPoint::withTrashed()->where('carrier_id', $carrier->id)
                ->whereIn('external_code', array_column($rows, 'external_code'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('external_code');

            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

            foreach ($rows as $row) {
                $current = $existing->get($row['external_code']);
                $expected = $row['expected_state'] ?? null;

                if ($current !== null) {
                    $this->assertCurrent($current, $expected);

                    if ($current->trashed()) {
                        $this->fail('file', 'Un bureau est archivé. Aucun remplacement automatique.');
                    }
                } elseif ($expected !== null) {
                    $this->fail('conflict', 'Un bureau de cet aperçu a disparu.');
                }

                $before = $current?->reference_version;
                $saved = $this->write($actor, $carrier, $areas, $this->validated($row), $current, true);
                $result[$this->outcome($before, $saved->reference_version)]++;
            }

            return $result;
        }, self::ATTEMPTS);
    }

    /**
     * Prépare les états attendus sans enregistrer de bureau.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function reviewRows(User $actor, ShippingCarrier $carrier, array $rows): array
    {
        $this->authorize($actor, 'import');

        $codes = array_column($rows, 'external_code');

        if (count(array_unique($codes)) !== count($codes)) {
            $this->fail('file', 'Un code de bureau apparaît plusieurs fois dans ce fichier.');
        }

        $areas = GeographicArea::selectable()->whereIn('uuid', $this->areaUuids($rows))->get()->keyBy('uuid');
        $existing = PickupPoint::withTrashed()->where('carrier_id', $carrier->id)
            ->whereIn('external_code', $codes)->get()->keyBy('external_code');

        $preview = [];

        foreach ($rows as $row) {
            // identityFor() garantit que la wilaya (et la commune éventuelle) existent et sont cohérentes.
            $identity = $this->identityFor($carrier, $areas, $row);
            $current = $existing->get($row['external_code']);

            if ($current !== null && ($current->trashed() || $current->only(array_keys($identity)) !== $identity)) {
                $this->fail('file', 'Un bureau est archivé ou possède une autre zone. Aucun remplacement automatique.');
            }

            $preview[] = [
                ...$row,
                'expected_state' => $current?->managementState(),
                'zone_label' => $areas->get($row['province_uuid'])->name_fr,
                'municipality_label' => $areas->get($row['municipality_uuid'] ?? '')?->name_fr,
                'operation' => $current !== null ? 'Mettre à jour, disponibilité conservée' : 'Créer, inactif',
            ];
        }

        return $preview;
    }

    /**
     * Valide les données et normalise les uuid en minuscules (null si vides).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validated(array $data): array
    {
        $data = Validator::make($data, self::dataRules())->validate();

        foreach (['carrier_uuid', 'province_uuid', 'municipality_uuid'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? strtolower($data[$field]) : null;
        }

        return $data;
    }

    /**
     * @param  Collection<string, GeographicArea>  $areas
     * @param  array<string, mixed>  $data
     */
    private function write(User $actor, ShippingCarrier $carrier, Collection $areas, array $data, ?PickupPoint $current, bool $import): PickupPoint
    {
        $identity = $this->identityFor($carrier, $areas, $data);

        if ($current !== null && $current->only(array_keys($identity)) !== $identity) {
            $this->fail('conflict', 'Le transporteur, le code et la zone d’un bureau existant ne peuvent pas changer.');
        }

        $point = $current ?? new PickupPoint;
        $before = $current?->only(PickupPoint::STATE_FIELDS);

        $point->fill(Arr::only($data, PickupPoint::EDITABLE));

        if ($current !== null && ! $point->isDirty()) {
            return $point;
        }

        $point->forceFill([
            ...$identity,
            'reference_version' => ($current->reference_version ?? 0) + 1,
            'is_carrier_active' => $current->is_carrier_active ?? false,
            'synced_at' => $import ? now() : $point->synced_at,
        ]);

        try {
            $point->save();
        } catch (UniqueConstraintViolationException) {
            $this->fail('external_code', 'Ce code de bureau existe déjà pour ce transporteur, y compris dans les archives.');
        }

        $this->audit($actor, $point, $current !== null ? self::EVENT_UPDATED : self::EVENT_CREATED, $before);

        return $point;
    }

    /**
     * Vérifie la wilaya et la commune, puis retourne les champs qui fixent l'identité du bureau.
     *
     * @param  Collection<string, GeographicArea>  $areas
     * @param  array<string, mixed>  $data
     * @return array{carrier_id: int, province_id: int, municipality_id: ?int, external_code: string}
     */
    private function identityFor(ShippingCarrier $carrier, Collection $areas, array $data): array
    {
        $province = $areas->get($data['province_uuid']);

        if ($province === null || $province->type !== GeoZoneTypeEnum::PROVINCE) {
            $this->fail('province_uuid', 'Sélectionnez une wilaya disponible.');
        }

        $municipality = null;

        if (filled($data['municipality_uuid'] ?? null)) {
            $municipality = $areas->get($data['municipality_uuid']);

            if ($municipality === null
                || $municipality->type !== GeoZoneTypeEnum::MUNICIPALITY
                || $municipality->parent_id !== $province->id) {
                $this->fail('municipality_uuid', 'La commune doit être disponible et appartenir à cette wilaya.');
            }
        }

        return [
            'carrier_id' => $carrier->id,
            'province_id' => $province->id,
            'municipality_id' => $municipality?->id,
            'external_code' => $data['external_code'],
        ];
    }

    /**
     * Applique les changements, incrémente la version de référence et retourne l'état d'avant.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function revise(PickupPoint $point, array $changes): array
    {
        $before = $point->only(PickupPoint::STATE_FIELDS);

        $point->forceFill($changes);
        $point->reference_version++;
        $point->save();

        return $before;
    }

    /** @return 'created'|'updated'|'unchanged' */
    private function outcome(?int $before, int $after): string
    {
        return match (true) {
            $before === null => 'created',
            $before === $after => 'unchanged',
            default => 'updated',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function areaUuids(array $rows): array
    {
        return array_values(array_filter([
            ...array_column($rows, 'province_uuid'),
            ...array_column($rows, 'municipality_uuid'),
        ]));
    }

    /**
     * Verrouille les pays puis les zones, toujours dans le même ordre, pour éviter les interblocages.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<string, GeographicArea>
     */
    private function lockAreas(array $rows): Collection
    {
        $areas = GeographicArea::withTrashed()->whereIn('uuid', $this->areaUuids($rows))->get(['id', 'country_id']);

        Country::whereIn('id', $areas->pluck('country_id'))->orderBy('id')->lockForUpdate()->get(['id']);

        return GeographicArea::selectable()->whereIn('id', $areas->pluck('id'))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('uuid');
    }

    /**
     * Exécute une modification sur le bureau verrouillé, après contrôle de l'état attendu.
     *
     * @param  Closure(PickupPoint): void  $mutation
     */
    private function mutate(User $actor, PickupPoint $point, string $ability, string $expected, Closure $mutation): void
    {
        $this->authorize($actor, $ability, $point);

        DB::transaction(function () use ($actor, $point, $ability, $expected, $mutation): void {
            $this->lockCarrier($point->carrier->uuid);
            $current = $this->lockPoint($point);

            $this->authorize($actor, $ability, $current);
            $this->assertCurrent($current, $expected);

            $mutation($current);
        }, self::ATTEMPTS);
    }

    private function lockCarrier(string $uuid): ShippingCarrier
    {
        return ShippingCarrier::where('uuid', $uuid)->lockForUpdate()->first()
            ?? $this->fail('carrier_uuid', 'Le transporteur est introuvable ou archivé.');
    }

    private function lockPoint(PickupPoint $point): PickupPoint
    {
        return PickupPoint::whereKey($point->id)->lockForUpdate()->firstOrFail();
    }

    private function assertCurrent(PickupPoint $point, ?string $expected): void
    {
        if ($expected === null || ! hash_equals($point->managementState(), $expected)) {
            $this->fail('conflict', 'Ce bureau a changé. Rechargez le formulaire ou l’aperçu.');
        }
    }

    private function authorize(User $actor, string $ability, ?PickupPoint $point = null): void
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize($ability, $point ?? PickupPoint::class);
    }

    /** @param array<string, mixed>|null $before */
    private function audit(User $actor, PickupPoint $point, string $event, ?array $before): void
    {
        activity()->causedBy($actor)->performedOn($point)->event($event)
            ->withProperties(['before' => $before, 'after' => $point->only(PickupPoint::STATE_FIELDS)])
            ->log('Référentiel des bureaux de retrait modifié.');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
