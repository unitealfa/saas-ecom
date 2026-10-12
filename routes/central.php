<?php

use App\Http\Middleware\Central\Auth\EnsureActiveCentralAccount;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

require __DIR__.'/central/diagnostics.php';

Route::middleware(['auth:central', 'verified', EnsureActiveCentralAccount::class])->group(function (): void {
    Route::view('dashboard', 'central.dashboard')->name('dashboard');

    require __DIR__.'/central/admin.php';
});

require __DIR__.'/central/settings.php';
