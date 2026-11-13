<?php

namespace App\Http\Controllers\Tenant\Admin\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Shop\ShopProfileActionRequest;
use App\Http\Requests\Tenant\Shop\StoreShopSocialLinkRequest;
use App\Models\Tenant\Shop\ShopSocialLink;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Shop\ShopProfileService;
use App\Services\Tenant\Shop\ShopSocialLinkService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShopSocialLinkController extends Controller
{
    public function __construct(private ShopProfileService $profiles, private ShopSocialLinkService $links) {}

    public function index(Request $request): View
    {
        return $this->page($request);
    }

    public function edit(Request $request, string $link): View
    {
        return $this->page($request, $link);
    }

    private function page(Request $request, ?string $uuid = null): View
    {
        $shop = $this->profiles->shop();
        $actor = $request->user('tenant');
        Gate::forUser($actor)->authorize('viewAny', [ShopSocialLink::class, $shop]);
        $editing = $uuid === null ? null : ShopSocialLink::query()->where('shop_id', $shop->id)->where('uuid', $uuid)->firstOrFail();
        if ($editing !== null) {
            Gate::forUser($actor)->authorize('update', $editing);
        }
        /** @var User $actor */
        $items = $shop->socialLinks()->orderBy('position')->orderBy('id')->get();

        return view('tenant.pages.admin.shop.social-links', [
            'shop' => $shop, 'items' => $items, 'editing' => $editing,
            'addresses' => $shop->addresses()->orderBy('position')->get(),
            'tokens' => $items->mapWithKeys(fn (ShopSocialLink $item): array => [$item->uuid => $this->links->token($item)]),
            'userName' => trim($actor->first_name.' '.$actor->last_name),
        ]);
    }

    public function store(StoreShopSocialLinkRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user('tenant');
        $this->links->save($actor, $request->validated());

        return redirect()->route('tenant.admin.shop.social-links.index')->with('status', 'Lien social enregistré.');
    }

    public function update(StoreShopSocialLinkRequest $request, string $link): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user('tenant');
        $this->links->save($actor, $request->validated(), $link);

        return redirect()->route('tenant.admin.shop.social-links.index')->with('status', 'Lien social modifié.');
    }

    public function destroy(ShopProfileActionRequest $request, string $link): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user('tenant');
        $this->links->archive($actor, $link, $request->validated('expected_state'));

        return redirect()->route('tenant.admin.shop.social-links.index')->with('status', 'Lien social archivé.');
    }
}
