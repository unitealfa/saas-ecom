<?php

namespace App\Http\Controllers\Tenant\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Users\ChangeMemberEmailRequest;
use App\Http\Requests\Tenant\Users\ChangeMemberRequest;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Users\TeamMemberService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class TeamMemberController extends Controller
{
    public function __construct(private readonly TeamMemberService $members) {}

    public function index(Request $request): View
    {
        Gate::forUser($request->user('tenant'))->authorize('viewAny', User::class);
        $query = User::withTrashed()->whereNull('central_user_uuid')->orderBy('last_name');
        if ($request->filled('search')) {
            $search = substr((string) $request->query('search'), 0, 100);
            $query->where(function ($q) use ($search): void {
                $q->where('email', 'like', '%'.addcslashes($search, '\\%_').'%')
                    ->orWhere('last_name', 'like', '%'.addcslashes($search, '\\%_').'%');
            });
        }

        return view('tenant.pages.admin.users.index', ['members' => $query->paginate(20)->withQueryString()]);
    }

    public function show(Request $request, User $member): View
    {
        Gate::forUser($request->user('tenant'))->authorize('view', $member);
        abort_unless($member->central_user_uuid === null, 404);

        return view('tenant.pages.admin.users.show', [
            'member' => $member, 'state' => $this->members->state($member),
        ]);
    }

    public function change(ChangeMemberRequest $request, User $member): RedirectResponse
    {
        $this->members->change($request->user('tenant'), $member, $request->validated());

        return to_route('tenant.admin.users.show', $member)->with('status', 'Membre mis à jour.');
    }

    public function changeEmail(ChangeMemberEmailRequest $request, User $member): RedirectResponse
    {
        $this->members->requestEmailChange($this->tenantActor(), $member, $request->validated('email'), $request->validated('expected_state'));

        return to_route('tenant.admin.users.show', $member)->with('status', 'Un lien de confirmation a été envoyé à la nouvelle adresse.');
    }
}
