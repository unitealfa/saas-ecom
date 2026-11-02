<?php

namespace App\Http\Controllers\Central\Admin\Subscriptions;

use App\Enums\Central\Subscriptions\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Subscriptions\SearchSubscriptionsRequest;
use App\Models\Central\Subscriptions\Subscription;
use App\Services\Central\Subscriptions\SubscriptionCatalogService;
use Illuminate\Contracts\View\View;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionCatalogService $subscriptions) {}

    public function index(SearchSubscriptionsRequest $request): View
    {
        $status = $request->validated('status');

        return view('central.pages.admin.subscriptions.index', [
            'subscriptions' => $this->subscriptions->paginate($this->centralActor(), $request->validated('search'), $status === null ? null : (int) $status),
            'statuses' => StatusEnum::cases(),
        ]);
    }

    public function show(Subscription $subscription): View
    {
        return view('central.pages.admin.subscriptions.show', [
            'subscription' => $this->subscriptions->details($this->centralActor(), $subscription),
        ]);
    }
}
