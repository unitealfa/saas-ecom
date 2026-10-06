<?php

use App\Http\Controllers\Central\DatabaseDiagnosticsController;
use App\Http\Middleware\EnsureLocalDiagnostics;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/_dev/database', DatabaseDiagnosticsController::class)
    ->middleware(EnsureLocalDiagnostics::class)->name('central.database-diagnostics');

Route::middleware(['auth:central', 'verified'])->group(function (): void {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
