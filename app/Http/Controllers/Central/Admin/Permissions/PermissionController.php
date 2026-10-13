<?php

namespace App\Http\Controllers\Central\Admin\Permissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Permissions\SearchPermissionsRequest;
use App\Http\Requests\Central\Permissions\UpdatePermissionRequest;
use App\Models\Central\Permission;
use App\Models\Central\User;
use App\Services\Central\Authorization\PermissionCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PermissionController extends Controller
{
    public function __construct(private PermissionCatalogService $catalog) {}

    public function index(SearchPermissionsRequest $request): View
    {
        $search = $request->validated('search');

        return view('central.pages.admin.permissions.index', [
            'permissions' => $this->catalog->paginate($this->actor($request), $search),
            'search' => $search,
            'groups' => $this->catalog->groups(),
        ]);
    }

    public function show(Permission $permission): View
    {
        Gate::authorize('view', $permission);

        return view('central.pages.admin.permissions.show', [
            'permission' => $permission, 'group' => $this->catalog->groups()[$permission->name] ?? 'Autres permissions centrales',
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $this->catalog->updateLabel($this->actor($request), $permission, $request->validated('label'));

        return redirect()->route('central.permissions.show', $permission)->with('status', 'Libellé de la permission enregistré.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
