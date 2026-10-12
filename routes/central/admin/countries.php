<?php

use App\Http\Controllers\Central\Admin\Countries\CountryController;
use Illuminate\Support\Facades\Route;

Route::get('admin/countries', [CountryController::class, 'index'])->name('central.countries.index');
Route::get('admin/countries/create', [CountryController::class, 'create'])->name('central.countries.create');
Route::post('admin/countries', [CountryController::class, 'store'])->name('central.countries.store');
Route::get('admin/countries/{country}/edit', [CountryController::class, 'edit'])->name('central.countries.edit');
Route::put('admin/countries/{country}', [CountryController::class, 'update'])->name('central.countries.update');
Route::patch('admin/countries/{country}/activation', [CountryController::class, 'activate'])->name('central.countries.activate');
Route::delete('admin/countries/{country}', [CountryController::class, 'destroy'])->name('central.countries.destroy');
