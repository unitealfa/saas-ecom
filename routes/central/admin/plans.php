<?php

use App\Http\Controllers\Central\Admin\Plans\PlanController;
use Illuminate\Support\Facades\Route;

Route::controller(PlanController::class)->prefix('plans')->name('plans.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('{plan}', 'show')->withTrashed()->name('show');
});
