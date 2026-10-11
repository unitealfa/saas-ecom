<?php

use App\Http\Controllers\Central\DatabaseDiagnosticsController;
use App\Http\Controllers\Central\RoleController;
use App\Http\Controllers\Central\TenantController;
use App\Http\Controllers\Central\UserController;
use App\Http\Controllers\Central\UserRoleController;
use App\Http\Middleware\Central\EnsureActiveCentralAccount;
use App\Http\Middleware\EnsureLocalDiagnostics;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::get('/_dev/database', DatabaseDiagnosticsController::class)
    ->middleware(EnsureLocalDiagnostics::class)->name('central.database-diagnostics');

Route::middleware(['auth:central', 'verified', EnsureActiveCentralAccount::class])->group(function (): void {
    Route::view('dashboard', 'central.dashboard')->name('dashboard');
    Route::livewire('admin/users', 'pages::users')
        ->middleware('can:viewAny,App\\Models\\User')->name('central.users.index');

    Route::get('admin/users/create', [UserController::class, 'create'])->name('central.users.create');
    Route::post('admin/users', [UserController::class, 'store'])->name('central.users.store');
    Route::get('admin/users/{user}', [UserController::class, 'show'])->name('central.users.show');
    Route::get('admin/users/{user}/edit', [UserController::class, 'edit'])->name('central.users.edit');
    Route::put('admin/users/{user}', [UserController::class, 'update'])->name('central.users.update');
    Route::post('admin/users/{user}/invitation', [UserController::class, 'resendInvitation'])
        ->middleware('throttle:6,1')->name('central.users.invitation');
    Route::get('admin/users/{user}/roles', [UserRoleController::class, 'edit'])->name('central.users.roles.edit');
    Route::put('admin/users/{user}/roles', [UserRoleController::class, 'update'])->name('central.users.roles.update');
    Route::get('admin/users/{user}/shops/create', [TenantController::class, 'create'])->name('central.users.tenants.create');
    Route::post('admin/users/{user}/shops', [TenantController::class, 'store'])->name('central.users.tenants.store');
    Route::get('admin/roles', [RoleController::class, 'index'])->name('central.roles.index');
    Route::get('admin/roles/create', [RoleController::class, 'create'])->name('central.roles.create');
    Route::post('admin/roles', [RoleController::class, 'store'])->name('central.roles.store');
    Route::get('admin/roles/{role}/edit', [RoleController::class, 'edit'])->name('central.roles.edit');
    Route::put('admin/roles/{role}', [RoleController::class, 'update'])->name('central.roles.update');
});

require __DIR__.'/central/settings.php';
