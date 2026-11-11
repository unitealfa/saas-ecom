<?php

use App\Http\Controllers\Central\Admin\Operations\OperationsController;
use Illuminate\Support\Facades\Route;

Route::get('operations', OperationsController::class)->name('operations.index');
