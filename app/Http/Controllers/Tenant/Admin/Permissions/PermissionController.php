<?php

namespace App\Http\Controllers\Tenant\Admin\Permissions;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Permissions\Permission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PermissionController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::forUser($request->user('tenant'))->authorize('viewAny', Permission::class);
        $permissions = Permission::query()->orderBy('name');
        if ($request->filled('search')) {
            $search = substr((string) $request->query('search'), 0, 100);
            $permissions->where('name', 'like', '%'.addcslashes($search, '\\%_').'%');
        }
        return view('tenant.pages.admin.permissions.index', ['permissions' => $permissions->paginate(20)->withQueryString()]);
    }
}
