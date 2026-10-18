<?php

use App\Http\Controllers\Central\Admin\Permissions\PermissionController;
use Illuminate\Support\Facades\Route;

Route::get('admin/permissions', [PermissionController::class, 'index'])->name('central.permissions.index');
