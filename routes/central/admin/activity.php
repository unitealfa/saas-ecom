<?php

use App\Http\Controllers\Central\Admin\Audit\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::controller(ActivityLogController::class)->prefix('activity')->name('activity.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('export', 'export')->name('export');
    Route::get('{activity}', 'show')->whereUuid('activity')->name('show');
});
