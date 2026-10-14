<?php

use App\Http\Controllers\Central\Admin\Tenants\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('admin/tenants', [TenantController::class, 'index'])->name('central.tenants.index');
Route::get('admin/tenants/create', [TenantController::class, 'chooseOwner'])->name('central.tenants.create');
Route::get('admin/tenants/{tenant}', [TenantController::class, 'show'])->withTrashed()->name('central.tenants.show');
Route::get('admin/tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('central.tenants.edit');
Route::put('admin/tenants/{tenant}', [TenantController::class, 'update'])->name('central.tenants.update');
Route::get('admin/tenants/{tenant}/history', [TenantController::class, 'history'])->withTrashed()->name('central.tenants.history');
Route::post('admin/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('central.tenants.suspend');
Route::post('admin/tenants/{tenant}/activate', [TenantController::class, 'activate'])->name('central.tenants.activate');
Route::post('admin/tenants/{tenant}/archive', [TenantController::class, 'archive'])->name('central.tenants.archive');
Route::put('admin/tenants/{tenant}/priorities', [TenantController::class, 'priorities'])->name('central.tenants.priorities');

Route::get('admin/users/{user}/shops/create', [TenantController::class, 'create'])->name('central.users.tenants.create');
Route::post('admin/users/{user}/shops', [TenantController::class, 'store'])->name('central.users.tenants.store');
