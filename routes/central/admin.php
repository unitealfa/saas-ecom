<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('central.')->group(function (): void {
    require __DIR__.'/admin/users.php';
    require __DIR__.'/admin/staff.php';
    require __DIR__.'/admin/tenants.php';
    require __DIR__.'/admin/roles.php';
    require __DIR__.'/admin/permissions.php';
    require __DIR__.'/admin/countries.php';
});
