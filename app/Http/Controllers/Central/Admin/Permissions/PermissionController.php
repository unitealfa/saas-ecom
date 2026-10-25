<?php

namespace App\Http\Controllers\Central\Admin\Permissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Permissions\SearchPermissionsRequest;
use App\Models\Central\User;
use App\Services\Central\Authorization\PermissionCatalogService;
use Illuminate\Contracts\View\View;

class PermissionController extends Controller
{
    public function __construct(private PermissionCatalogService $catalog) {}

    public function index(SearchPermissionsRequest $request): View
    {
        $search = $request->validated('search');
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);

        return view('central.pages.admin.permissions.index', [
            'permissions' => $this->catalog->paginate($actor, $search),
            'search' => $search,
            'groups' => $this->catalog->groups(),
        ]);
    }
}