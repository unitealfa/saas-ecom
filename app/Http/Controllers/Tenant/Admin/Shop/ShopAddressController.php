<?php

namespace App\Http\Controllers\Tenant\Admin\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Shop\ShopProfileActionRequest;
use App\Http\Requests\Tenant\Shop\StoreShopAddressRequest;
use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Tenant\Shop\ShopAddress;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Shop\ShopAddressService;
use App\Services\Tenant\Shop\ShopProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShopAddressController extends Controller
{
    public function __construct(private ShopProfileService $profiles, private ShopAddressService $addresses) {}

    public function index(Request $request): View
    {
        return $this->page($request);
    }

    public function edit(Request $request, string $address): View
    {
        return $this->page($request, $address);
    }

    private function page(Request $request, ?string $uuid = null): View
    {
        $shop = $this->profiles->shop();
        $actor = $request->user('tenant');
        Gate::forUser($actor)->authorize('viewAny', [ShopAddress::class, $shop]);
        $editing = $uuid === null ? null : ShopAddress::query()->where('shop_id', $shop->id)->where('uuid', $uuid)->firstOrFail();
        if ($editing !== null) {
            Gate::forUser($actor)->authorize('update', $editing);
        }
        /** @var User $actor */
        $items = $shop->addresses()->orderBy('position')->orderBy('id')->get();

        return view('tenant.pages.admin.shop.addresses', [
            'shop' => $shop, 'items' => $items, 'editing' => $editing,
            'provinces' => GeographicArea::selectable()->where('type', GeoZoneTypeEnum::PROVINCE)->orderBy('name_fr')->get(['id', 'uuid', 'name_fr']),
            'municipalitiesByProvince' => GeographicArea::selectable()->where('type', GeoZoneTypeEnum::MUNICIPALITY)->with('parent:id,uuid')->orderBy('name_fr')->get(['id', 'uuid', 'name_fr', 'parent_id'])->groupBy(fn (GeographicArea $area): string => $area->parent?->uuid ?? ''),
            'tokens' => $items->mapWithKeys(fn (ShopAddress $item): array => [$item->uuid => $this->addresses->token($item)]),
            'userName' => trim($actor->first_name.' '.$actor->last_name),
        ]);
    }

    public function store(StoreShopAddressRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user('tenant');
        $this->addresses->save($actor, $request->validated());

        return redirect()->route('tenant.admin.shop.addresses.index')->with('status', 'Adresse enregistrée.');
    }

    public function update(StoreShopAddressRequest $request, string $address): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user('tenant');
        $this->addresses->save($actor, $request->validated(), $address);

        return redirect()->route('tenant.admin.shop.addresses.index')->with('status', 'Adresse modifiée.');
    }

    public function destroy(ShopProfileActionRequest $request, string $address): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user('tenant');
        $this->addresses->archive($actor, $address, $request->validated('expected_state'));

        return redirect()->route('tenant.admin.shop.addresses.index')->with('status', 'Adresse archivée.');
    }
}
