<?php

use App\Http\Controllers\Tenant\Admin\Roles\RoleController;
use Illuminate\Support\Facades\Route;

Route::controller(RoleController::class)->prefix('admin/roles')->name('admin.roles.')->whereUuid('role')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{role}', 'show')->name('show');
    Route::get('{role}/edit', 'edit')->name('edit');
    Route::put('{role}', 'update')->name('update');
    Route::delete('{role}', 'destroy')->name('destroy');
});
