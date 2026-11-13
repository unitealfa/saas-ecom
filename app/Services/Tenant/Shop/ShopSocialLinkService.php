<?php

namespace App\Services\Tenant\Shop;

use App\Enums\Tenant\Shop\ShopProfileRecordTypeEnum;
use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Shop\ShopAddress;
use App\Models\Tenant\Shop\ShopSocialLink;
use App\Models\Tenant\Users\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ShopSocialLinkService
{
    public function __construct(private ShopProfileCatalog $catalog) {}

    public function token(ShopSocialLink $link): string
    {
        return hash('sha256', json_encode($link->getRawOriginal(), JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?string $uuid = null): void
    {
        abort_unless(tenancy()->initialized, 403);

        DB::connection('tenant')->transaction(function () use ($actor, $data, $uuid): void {
            $shop = Shop::query()->where('singleton', 1)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->getKey())->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
            abort_unless($shop->tenant_uuid === tenant()->uuid, 403);
            $link = $uuid === null ? null : ShopSocialLink::query()->where('shop_id', $shop->id)
                ->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($link === null ? 'create' : 'update', $link ?? [ShopSocialLink::class, $shop]);
            if ($link !== null && ! hash_equals($this->token($link), (string) $data['expected_state'])) {
                throw ValidationException::withMessages(['expected_state' => 'Lien modifié entre-temps. Rechargez la page.']);
            }
            ShopPublicUrl::assertSafe($data['url'], 'url');
            $addressId = null;
            if (filled($data['address_uuid'] ?? null)) {
                $address = ShopAddress::query()->where('shop_id', $shop->id)
                    ->where('uuid', $data['address_uuid'])->first();
                if ($address === null) {
                    throw ValidationException::withMessages(['address_uuid' => 'Lieu inconnu ou archivé.']);
                }
                $addressId = $address->id;
            }
            if ($link === null) {
                $link = new ShopSocialLink;
                $link->forceFill(['shop_id' => $shop->id, 'record_type' => ShopProfileRecordTypeEnum::SOCIAL,
                    'is_primary' => false]);
            }
            $link->forceFill([
                'shop_address_id' => $addressId, 'province_uuid' => null, 'municipality_uuid' => null,
                'label' => $data['label'] ?? null, 'position' => $data['position'],
                'visible' => (bool) $data['visible'],
                'payload' => ['schema_version' => 1, 'network' => $data['network'], 'url' => $data['url']],
            ])->save();
            activity()->causedBy($actor)->performedOn($link)->event('shop_social_link_saved')
                ->withProperties(['uuid' => $link->uuid, 'network' => $data['network']])->log('Réseau social enregistré.');
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
            $link = ShopSocialLink::query()->where('shop_id', $shop->id)->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('archive', $link);
            if (! hash_equals($this->token($link), $expectedState)) {
                throw ValidationException::withMessages(['expected_state' => 'Lien modifié entre-temps. Rechargez la page.']);
            }
            $link->delete();
            activity()->causedBy($actor)->performedOn($link)->event('shop_social_link_archived')
                ->withProperties(['uuid' => $link->uuid])->log('Lien social archivé.');
            $this->catalog->invalidateAfterCommit($shop);
        }, 3);
    }
}
