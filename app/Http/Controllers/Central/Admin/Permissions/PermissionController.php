<?php

namespace App\Http\Controllers\Central\Admin\Permissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Permissions\SearchPermissionsRequest;
use App\Models\Central\User;
use App\Services\Central\Authorization\PermissionCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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

    private function actor(Request $request): User
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
