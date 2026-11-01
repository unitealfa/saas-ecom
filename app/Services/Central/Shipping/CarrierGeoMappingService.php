<?php

namespace App\Services\Central\Shipping;

use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Shipping\CarrierGeoMapping;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CarrierGeoMappingService
{
    private const int PER_PAGE = 20;

    private const int ATTEMPTS = 3;

    private const string CREATED = 'carrier_mapping_created';

    private const string UPDATED = 'carrier_mapping_updated';

    private const string ARCHIVED = 'carrier_mapping_archived';

    private const string IMPORTED = 'carrier_mapping_imported';

    private const array AUDITED = ['uuid', 'carrier_id', 'geographic_area_id', 'zone_type',
        ...CarrierGeoMapping::EDITABLE, 'is_active', 'mapping_version', 'verified_at', 'synced_at'];

    /** @return array<string, mixed> */
    public static function dataRules(): array
    {
        return ['carrier_uuid' => ['required', 'uuid'], 'geographic_area_uuid' => ['required', 'uuid'],
            ...array_fill_keys(CarrierGeoMapping::EDITABLE, ['required', 'string', 'max:255'])];
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, CarrierGeoMapping>
     */
    public function search(User $actor, array $filters): LengthAwarePaginator
    {
        $this->authorize($actor, 'viewAny');
        $pattern = '%'.addcslashes(trim((string) ($filters['search'] ?? '')), '\\%_').'%';

        return CarrierGeoMapping::query()->with(['carrier', 'area'])
            ->when($filters['carrier_uuid'] ?? null, fn (Builder $query, string $uuid) => $query->whereRelation('carrier', 'uuid', $uuid))
            ->when(isset($filters['is_active']), fn (Builder $query) => $query->where('is_active', (bool) $filters['is_active']))
            ->when($filters['search'] ?? null, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('external_code', 'like', $pattern)->orWhere('external_name', 'like', $pattern)
                ->orWhereHas('area', fn (Builder $query) => $query->where('name_fr', 'like', $pattern))))
            ->orderBy('carrier_id')->orderBy('geographic_area_id')->paginate(self::PER_PAGE)->withQueryString();
    }

    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?CarrierGeoMapping $mapping = null, ?string $expected = null): CarrierGeoMapping
    {
        $ability = $mapping === null ? 'create' : 'update';
        $this->authorize($actor, $ability, $mapping);
        $data = Validator::make($data, self::dataRules())->validate();
        $data['geographic_area_uuid'] = strtolower($data['geographic_area_uuid']);

        return DB::transaction(function () use ($actor, $ability, $data, $mapping, $expected): CarrierGeoMapping {
            $carrier = $this->lockCarrier($data['carrier_uuid']);
            $current = $mapping === null ? null : CarrierGeoMapping::query()->whereKey($mapping->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $ability, $current);
            if ($current !== null) {
                $this->assertCurrent($current, $expected);
                if ($current->carrier_id !== $carrier->id || $current->area->uuid !== $data['geographic_area_uuid']) {
                    $this->fail('mapping', 'Le transporteur et la zone d’une correspondance sont permanents.');
                }
            }

            return $this->write($actor, $carrier, $data, $current, false);
        }, self::ATTEMPTS);
    }

    public function archive(User $actor, CarrierGeoMapping $mapping, string $expected): void
    {
        $this->authorize($actor, 'archive', $mapping);
        DB::transaction(function () use ($actor, $mapping, $expected): void {
            $this->lockCarrier($mapping->carrier->uuid);
            $current = CarrierGeoMapping::query()->whereKey($mapping->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, 'archive', $current);
            $this->assertCurrent($current, $expected);
            $before = $current->only(self::AUDITED);
            $current->forceFill(['is_active' => false, 'mapping_version' => $current->mapping_version + 1])->save();
            $this->audit($actor, $current, self::ARCHIVED, $before);
        }, self::ATTEMPTS);
    }

    /** @param list<array<string, mixed>> $rows
     * @return array{created: int, updated: int, unchanged: int}
     */
    public function import(User $actor, string $carrierUuid, array $rows): array
    {
        $this->authorize($actor, 'import');

        return DB::transaction(function () use ($actor, $carrierUuid, $rows): array {
            $carrier = $this->lockCarrier($carrierUuid);
            $this->authorize($actor, 'import');
            $areas = $this->lockAreas(array_column($rows, 'geographic_area_uuid'));
            $existing = CarrierGeoMapping::query()->where('carrier_id', $carrier->id)
                ->whereIn('geographic_area_id', $areas->pluck('id'))
                ->orderBy('geographic_area_id')->lockForUpdate()->get()->keyBy('geographic_area_id');
            $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
            foreach ($rows as $row) {
                $data = Validator::make([...$row, 'carrier_uuid' => $carrierUuid], self::dataRules())->validate();
                $data['geographic_area_uuid'] = strtolower($data['geographic_area_uuid']);
                $area = $areas->get($data['geographic_area_uuid']);
                if ($area === null) {
                    $this->fail('geographic_area_uuid', 'La zone de l’aperçu n’existe plus. Recommencez l’import.');
                }
                $current = $existing->get($area->id);
                if ($current !== null) {
                    $this->assertCurrent($current, $row['expected_state'] ?? null);
                    if (! $current->is_active) {
                        $this->fail('file', 'Une correspondance archivée ne peut pas être remplacée par un import.');
                    }
                } elseif (($row['expected_state'] ?? null) !== null) {
                    $this->fail('conflict', 'Une correspondance de l’aperçu a disparu. Recommencez l’import.');
                }
                $before = $current?->mapping_version;
                $saved = $this->write($actor, $carrier, $data, $current, true, $area);
                $counts[$before === null ? 'created' : ($saved->mapping_version === $before ? 'unchanged' : 'updated')]++;
            }

            return $counts;
        }, self::ATTEMPTS);
    }

    /** @param array<string, mixed> $data */
    private function write(User $actor, ShippingCarrier $carrier, array $data, ?CarrierGeoMapping $current, bool $import, ?GeographicArea $lockedArea = null): CarrierGeoMapping
    {
        $area = $lockedArea ?? $this->lockAreas([$data['geographic_area_uuid']])->get($data['geographic_area_uuid']);
        if ($area === null) {
            $this->fail('geographic_area_uuid', 'Sélectionnez une zone active dont le pays et la wilaya sont disponibles.');
        }
        $mapping = $current ?? new CarrierGeoMapping;
        $before = $mapping->exists ? $mapping->only(self::AUDITED) : null;
        $mapping->fill(Arr::only($data, CarrierGeoMapping::EDITABLE));
        if ($mapping->exists && ! $mapping->isDirty()) {
            return $mapping;
        }
        $mapping->forceFill([
            'carrier_id' => $carrier->id, 'geographic_area_id' => $area->id, 'zone_type' => $area->type,
            'mapping_version' => ($current->mapping_version ?? 0) + 1,
            'is_active' => true, 'verified_at' => now(),
            'synced_at' => $import ? now() : $mapping->synced_at,
        ]);
        try {
            $mapping->save();
        } catch (UniqueConstraintViolationException) {
            $this->fail('geographic_area_uuid', 'Cette zone possède déjà une correspondance pour ce transporteur.');
        }
        $this->audit($actor, $mapping, $import ? self::IMPORTED : ($before === null ? self::CREATED : self::UPDATED), $before);

        return $mapping;
    }

    /** @param list<string> $uuids
     * @return Collection<string, GeographicArea>
     */
    private function lockAreas(array $uuids): Collection
    {
        $areas = GeographicArea::withTrashed()->whereIn('uuid', $uuids)->get(['id', 'country_id']);
        Country::query()->whereIn('id', $areas->pluck('country_id'))->orderBy('id')->lockForUpdate()->get(['id']);

        return GeographicArea::selectable()->whereIn('id', $areas->pluck('id'))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('uuid');
    }

    private function lockCarrier(string $uuid): ShippingCarrier
    {
        $carrier = ShippingCarrier::query()->where('uuid', $uuid)->lockForUpdate()->first();
        if ($carrier === null) {
            $this->fail('carrier_uuid', 'Ce transporteur est introuvable ou archivé.');
        }

        return $carrier;
    }

    private function assertCurrent(CarrierGeoMapping $mapping, ?string $expected): void
    {
        if ($expected === null || ! hash_equals($mapping->managementState(), $expected)) {
            $this->fail('conflict', 'Cette correspondance a changé. Rechargez le formulaire ou l’aperçu.');
        }
    }

    private function authorize(User $actor, string $ability, ?CarrierGeoMapping $mapping = null): void
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize($ability, $mapping ?? CarrierGeoMapping::class);
    }

    /** @param array<string, mixed>|null $before */
    private function audit(User $actor, CarrierGeoMapping $mapping, string $event, ?array $before): void
    {
        activity()->causedBy($actor)->performedOn($mapping)->event($event)
            ->withProperties(['before' => $before, 'after' => $mapping->only(self::AUDITED)])
            ->log('Correspondance géographique du transporteur modifiée.');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
