<?php

use App\Http\Controllers\Central\Admin\Staff\StaffController;
use App\Http\Controllers\Central\Admin\Staff\StaffPermissionController;
use App\Http\Controllers\Central\Admin\Staff\StaffRoleController;
use Illuminate\Support\Facades\Route;

Route::livewire('admin/staff', 'central::pages.admin.staff.index')
    ->middleware('can:viewAnyStaff,App\\Models\\User')->name('central.staff.index');
Route::get('admin/staff/create', [StaffController::class, 'create'])->name('central.staff.create');
Route::post('admin/staff', [StaffController::class, 'store'])->name('central.staff.store');
Route::get('admin/staff/{user}', [StaffController::class, 'show'])->withTrashed()->name('central.staff.show');
Route::get('admin/staff/{user}/edit', [StaffController::class, 'edit'])->name('central.staff.edit');
Route::put('admin/staff/{user}', [StaffController::class, 'update'])->name('central.staff.update');
Route::post('admin/staff/{user}/invitation', [StaffController::class, 'resendInvitation'])
    ->middleware('throttle:6,1')->name('central.staff.invitation');
Route::patch('admin/staff/{user}/status', [StaffController::class, 'changeStatus'])->name('central.staff.status');
Route::delete('admin/staff/{user}', [StaffController::class, 'destroy'])->name('central.staff.destroy');
Route::get('admin/staff/{user}/roles', [StaffRoleController::class, 'edit'])->name('central.staff.roles.edit');
Route::put('admin/staff/{user}/roles', [StaffRoleController::class, 'update'])->name('central.staff.roles.update');
Route::post('admin/staff/{user}/roles/{role}/renew', [StaffRoleController::class, 'renew'])->name('central.staff.roles.renew');
Route::get('admin/staff/{user}/permissions', [StaffPermissionController::class, 'index'])->withTrashed()->name('central.staff.permissions.index');
