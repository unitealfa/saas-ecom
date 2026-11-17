<?php

use App\Http\Controllers\Tenant\Admin\Permissions\PermissionController;
use Illuminate\Support\Facades\Route;

Route::get('admin/permissions', PermissionController::class)->name('admin.permissions.index');
