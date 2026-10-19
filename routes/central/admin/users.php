<?php

use App\Http\Controllers\Central\Admin\Users\LegalVerificationController;
use App\Http\Controllers\Central\Admin\Users\UserApprovalController;
use App\Http\Controllers\Central\Admin\Users\UserController;
use App\Http\Controllers\Central\Admin\Users\UserRoleController;
use Illuminate\Support\Facades\Route;


Route::livewire('admin/users', 'central::pages.admin.users.index')
    ->middleware('can:viewAny,App\\Models\\User')->name('central.users.index');

Route::get('admin/users/create', [UserController::class, 'create'])->name('central.users.create');
Route::post('admin/users', [UserController::class, 'store'])->name('central.users.store');
Route::get('admin/users/pending', [UserApprovalController::class, 'index'])->name('central.users.pending');
Route::post('admin/users/{user}/approve', [UserApprovalController::class, 'approve'])->name('central.users.approve');
Route::post('admin/users/{user}/reject', [UserApprovalController::class, 'reject'])->name('central.users.reject');
Route::get('admin/users/{user}', [UserController::class, 'show'])->withTrashed()->name('central.users.show');
Route::get('admin/users/{user}/history', [UserController::class, 'history'])->withTrashed()->name('central.users.history');
Route::post('admin/users/{user}/legal/validate', [LegalVerificationController::class, 'validateProfile'])->name('central.users.legal.validate');
Route::post('admin/users/{user}/legal/reject', [LegalVerificationController::class, 'rejectProfile'])->name('central.users.legal.reject');
Route::get('admin/users/{user}/edit', [UserController::class, 'edit'])->name('central.users.edit');
Route::put('admin/users/{user}', [UserController::class, 'update'])->name('central.users.update');
Route::patch('admin/users/{user}/status', [UserController::class, 'changeStatus'])->name('central.users.status');
Route::delete('admin/users/{user}', [UserController::class, 'destroy'])->name('central.users.destroy');
Route::post('admin/users/{user}/invitation', [UserController::class, 'resendInvitation'])
    ->middleware('throttle:6,1')->name('central.users.invitation');
Route::get('admin/users/{user}/roles', [UserRoleController::class, 'edit'])->name('central.users.roles.edit');
Route::put('admin/users/{user}/roles', [UserRoleController::class, 'update'])->name('central.users.roles.update');
