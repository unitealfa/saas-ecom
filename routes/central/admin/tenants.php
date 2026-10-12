<?php

use App\Http\Controllers\Central\Admin\Tenants\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('admin/users/{user}/shops/create', [TenantController::class, 'create'])->name('central.users.tenants.create');
Route::post('admin/users/{user}/shops', [TenantController::class, 'store'])->name('central.users.tenants.store');
