<?php

namespace App\Http\Controllers\Central\Admin\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Operations\ReconcileOperationsRequest;
use App\Models\Central\Users\User;
use App\Services\Central\Operations\OperationsReader;
use App\Services\Central\Operations\OperationsReconciliation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class OperationsController extends Controller
{
    public function __construct(private readonly OperationsReconciliation $reconciliation) {}

    public function __invoke(OperationsReader $operations): View
    {
        $actor = $this->centralActor();
        $gate = Gate::forUser($actor);
        abort_unless($gate->any(['viewOperations', 'reconcileSubscriptions', 'reconcilePayments'], User::class), 403);

        return view('central.pages.admin.operations.index', $gate->allows('viewOperations', User::class)
            ? $operations->overview($actor) : ['deployments' => null, 'deliveries' => null]);
    }

    public function reconcileSubscriptions(ReconcileOperationsRequest $request): RedirectResponse
    {
        return to_route('central.operations.index')->with('operations_result',
            $this->reconciliation->subscriptions($this->centralActor(), $request->validated('cursor')));
    }

    public function reconcilePayments(ReconcileOperationsRequest $request): RedirectResponse
    {
        return to_route('central.operations.index')->with('operations_result',
            $this->reconciliation->payments($this->centralActor(), $request->validated('cursor')));
    }
}
