<?php

use App\Http\Controllers\Central\Admin\Roles\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('admin/roles', [RoleController::class, 'index'])->name('central.roles.index');
Route::get('admin/roles/create', [RoleController::class, 'create'])->name('central.roles.create');
Route::post('admin/roles', [RoleController::class, 'store'])->name('central.roles.store');
Route::get('admin/roles/{role}', [RoleController::class, 'show'])->name('central.roles.show');
Route::get('admin/roles/{role}/edit', [RoleController::class, 'edit'])->name('central.roles.edit');
Route::put('admin/roles/{role}', [RoleController::class, 'update'])->name('central.roles.update');
Route::delete('admin/roles/{role}', [RoleController::class, 'destroy'])->name('central.roles.destroy');
