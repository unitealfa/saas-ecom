<?php

use App\Http\Controllers\Central\Admin\Permissions\PermissionController;
use Illuminate\Support\Facades\Route;

Route::get('admin/permissions', [PermissionController::class, 'index'])->name('central.permissions.index');
Route::get('admin/permissions/{permission}', [PermissionController::class, 'show'])->name('central.permissions.show');
Route::put('admin/permissions/{permission}', [PermissionController::class, 'update'])->name('central.permissions.update');
