<?php

use App\Http\Controllers\Tenant\Admin\Users\TeamInvitationController;
use App\Http\Controllers\Tenant\Admin\Users\TeamMemberController;
use App\Http\Controllers\Tenant\Admin\Users\UserRoleController;
use App\Http\Controllers\Tenant\Admin\Users\UserPermissionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/team')->name('admin.')->group(function (): void {
    Route::controller(TeamInvitationController::class)->prefix('invitations')->name('team.invitations.')->whereUuid('invitation')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('{invitation}/revoke', 'revoke')->name('revoke');
        Route::post('{invitation}/resend', 'resend')->name('resend');
    });
    Route::controller(TeamMemberController::class)->prefix('users')->name('users.')->whereUuid('member')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('{member}', 'show')->withTrashed()->name('show');
        Route::patch('{member}', 'change')->name('update');
    });
    Route::controller(UserRoleController::class)->prefix('users/{member}/roles')->name('users.')->whereUuid('member')->group(function (): void {
        Route::get('/', 'show')->name('roles');
        Route::post('/', 'change')->name('roles.change');
    });
    Route::controller(UserPermissionController::class)->prefix('users/{member}/permissions')->name('users.')->whereUuid('member')->group(function (): void {
        Route::get('/', 'show')->name('permissions');
        Route::post('/', 'change')->name('permissions.change');
    });
});
