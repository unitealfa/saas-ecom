<?php

namespace App\Http\Controllers\Central\Admin\Audit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Audit\SearchActivityRequest;
use App\Models\Central\Audit\Activity;
use App\Services\Central\Audit\CentralActivityService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function __construct(private readonly CentralActivityService $activities) {}

    public function index(SearchActivityRequest $request): View
    {
        return view('central.pages.admin.activity-log.index', ['activities' => $this->activities->search($this->centralActor(), $request->validated())]);
    }

    public function show(Activity $activity): View
    {
        return view('central.pages.admin.activity-log.show', $this->activities->details($this->centralActor(), $activity));
    }

    public function export(SearchActivityRequest $request): StreamedResponse
    {
        return $this->activities->export($this->centralActor(), $request->validated());
    }
}
