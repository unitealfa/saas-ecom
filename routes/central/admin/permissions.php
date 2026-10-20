<?php

use App\Http\Controllers\Central\Admin\Permissions\PermissionController;
use Illuminate\Support\Facades\Route;

Route::controller(PermissionController::class)->prefix('permissions')->name('permissions.')->group(function (): void {
    Route::get('/', 'index')->name('index');
});
