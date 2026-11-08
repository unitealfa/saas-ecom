<?php

namespace App\Http\Controllers\Central\Owner\Billing;

use App\Http\Controllers\Controller;
use App\Services\Central\Billing\OwnerBillingOverviewService;
use Illuminate\Contracts\View\View;

final class OwnerBillingOverviewController extends Controller
{
    public function __invoke(OwnerBillingOverviewService $overview): View
    {
        return view(
            'central.pages.owner.billing.overview',
            $overview->show($this->centralActor()),
        );
    }
}
