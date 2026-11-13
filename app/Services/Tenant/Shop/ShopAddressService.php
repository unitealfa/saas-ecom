<?php

namespace App\Services\Tenant\Shop;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Enums\Tenant\Shop\ShopProfileRecordTypeEnum;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Shop\ShopAddress;
use App\Models\Tenant\Users\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ShopAddressService
{
    public function __construct(private ShopProfileCatalog $catalog) {}

    public function token(ShopAddress $address): string
    {
        return hash('sha256', json_encode($address->getRawOriginal(), JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?string $uuid = null): void
    {
        abort_unless(tenancy()->initialized, 403);

        DB::connection('tenant')->transaction(function () use ($actor, $data, $uuid): void {
            $shop = Shop::query()->where('singleton', 1)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->getKey())->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
            abort_unless($shop->tenant_uuid === tenant()->uuid, 403);
            $address = $uuid === null ? null : ShopAddress::query()->where('shop_id', $shop->id)
                ->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($address === null ? 'create' : 'update', $address ?? [ShopAddress::class, $shop]);
            if ($address !== null && ! hash_equals($this->token($address), (string) $data['expected_state'])) {
                throw ValidationException::withMessages(['expected_state' => 'Adresse modifiée entre-temps. Rechargez la page.']);
            }
            $this->validateGeography($data);
            if (filled($data['map_url'] ?? null)) {
                ShopPublicUrl::assertSafe($data['map_url'], 'map_url');
            }
            $payload = [
                'schema_version' => 1,
                'address' => $data['address'],
                'postal_code' => $data['postal_code'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'map_url' => $data['map_url'] ?? null,
                'phone' => $data['phone'] ?? null,
                'opening_hours' => ShopOpeningHours::validate($data['opening_hours'] ?? null),
            ];
            if ($address === null) {
                $address = new ShopAddress;
                $address->forceFill(['shop_id' => $shop->id, 'shop_address_id' => null,
                    'record_type' => ShopProfileRecordTypeEnum::ADDRESS]);
            }
            if ((bool) $data['is_primary']) {
                ShopAddress::query()->where('shop_id', $shop->id)->where('is_primary', true)
                    ->when($address->exists, fn ($q) => $q->where('id', '!=', $address->id))
                    ->update(['is_primary' => false]);
            }
            $address->forceFill([
                'label' => $data['label'], 'position' => $data['position'],
                'is_primary' => (bool) $data['is_primary'], 'visible' => (bool) $data['visible'],
                'province_uuid' => $data['province_uuid'] ?? null,
                'municipality_uuid' => $data['municipality_uuid'] ?? null,
                'payload' => $payload,
            ])->save();
            activity()->causedBy($actor)->performedOn($address)->event('shop_address_saved')
                ->withProperties(['uuid' => $address->uuid, 'primary' => $address->is_primary])->log('Adresse publique enregistrée.');
            $this->catalog->invalidateAfterCommit($shop);
        }, 3);
    }

    public function archive(User $actor, string $uuid, string $expectedState): void
    {
        abort_unless(tenancy()->initialized, 403);

        DB::connection('tenant')->transaction(function () use ($actor, $uuid, $expectedState): void {
            $shop = Shop::query()->where('singleton', 1)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->getKey())->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
            abort_unless($shop->tenant_uuid === tenant()->uuid, 403);
            $address = ShopAddress::query()->where('shop_id', $shop->id)->where('uuid', $uuid)
                ->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('archive', $address);
            if (! hash_equals($this->token($address), $expectedState)) {
                throw ValidationException::withMessages(['expected_state' => 'Adresse modifiée entre-temps. Rechargez la page.']);
            }
            $address->delete();
            activity()->causedBy($actor)->performedOn($address)->event('shop_address_archived')
                ->withProperties(['uuid' => $address->uuid])->log('Adresse publique archivée.');
            $this->catalog->invalidateAfterCommit($shop);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function validateGeography(array $data): void
    {
        $province = $data['province_uuid'] ?? null;
        $municipality = $data['municipality_uuid'] ?? null;
        if (! filled($province) || ! filled($municipality)) {
            throw ValidationException::withMessages(['province_uuid' => 'La wilaya et la commune sont obligatoires pour une adresse.']);
        }
        $parent = GeographicArea::query()->selectable()
            ->where('uuid', $province)->where('type', GeoZoneTypeEnum::PROVINCE)->first();
        if ($parent === null) {
            throw ValidationException::withMessages(['province_uuid' => 'Wilaya inconnue ou inactive.']);
        }
        if (! GeographicArea::query()->selectable()
            ->where('uuid', $municipality)->where('type', GeoZoneTypeEnum::MUNICIPALITY)
            ->where('parent_id', $parent->id)->exists()) {
            throw ValidationException::withMessages(['municipality_uuid' => 'Commune inconnue, inactive ou hors de cette wilaya.']);
        }
    }
}
