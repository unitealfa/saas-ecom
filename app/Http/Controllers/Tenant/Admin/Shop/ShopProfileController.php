<?php

namespace App\Http\Controllers\Tenant\Admin\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Shop\UpdateShopProfileRequest;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Shop\ShopProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShopProfileController extends Controller
{
    public function __construct(private ShopProfileService $profiles) {}

    public function index(Request $request): View
    {
        $shop = $this->profiles->shop();
        Gate::forUser($request->user('tenant'))->authorize('view', $shop);
        /** @var User $actor */
        $actor = $request->user('tenant');

        return view('tenant.pages.admin.shop.profile', [
            'shop' => $shop, 'expectedState' => $this->profiles->token($shop),
            'userName' => trim($actor->first_name.' '.$actor->last_name),
        ]);
    }

    public function update(UpdateShopProfileRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user('tenant');
        $this->profiles->update($actor, $request->validated(), $request->file('logo'), $request->file('favicon'));

        return redirect()->route('tenant.admin.shop.profile')->with('status', 'Profil de la boutique enregistré.');
    }
}
