<?php

namespace App\Http\Controllers\Central\Admin\Operations;

use App\Http\Controllers\Controller;
use App\Services\Central\Operations\OperationsReader;
use Illuminate\Contracts\View\View;

class OperationsController extends Controller
{
    public function __invoke(OperationsReader $operations): View
    {
        return view('central.pages.admin.operations.index', $operations->overview($this->centralActor()));
    }
}
