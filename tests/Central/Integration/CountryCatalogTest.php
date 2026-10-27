<?php

use App\Models\Central\Countries\Country;
use App\Services\Central\Countries\CountryCatalog;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

pest()->extend(TestCase::class);

beforeEach(function (): void {
    Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
    $this->seed(CountrySeeder::class);
    $this->catalog = app(CountryCatalog::class);
    $this->catalog->invalidate();
});

test('country selectors reuse one cached query and return independent models', function (): void {
    $db = (new Country)->getConnection();
    $db->enableQueryLog();
    try {
        $countries = $this->catalog->active();
        expect($db->getQueryLog())->toHaveCount(1);
        $countries->first()->name_fr = 'Unsaved change';
        $db->flushQueryLog();

        expect($this->catalog->active()->first()->name_fr)->not->toBe('Unsaved change');
        $this->catalog->forProfile(null);
        $this->catalog->forPhone(null);
        expect($db->getQueryLog())->toBe([]);
    } finally {
        $db->disableQueryLog();
        $db->flushQueryLog();
    }
});

test('country activation edits deletion and restoration invalidate cached selectors', function (): void {
    $country = Country::where('code', 'FR')->firstOrFail();
    expect($this->catalog->active()->contains('id', $country->id))->toBeFalse();
    expect($this->catalog->forProfile($country->id)->contains('id', $country->id))->toBeTrue();

    $country->forceFill(['is_active' => true, 'name_fr' => 'France mise à jour'])->save();
    expect($this->catalog->active()->find($country->id)->name_fr)->toBe('France mise à jour');

    $country->delete();
    expect($this->catalog->active()->contains('id', $country->id))->toBeFalse();
    expect($this->catalog->forProfile($country->id)->contains('id', $country->id))->toBeFalse();
    expect($this->catalog->forPhone($country->id)->contains('id', $country->id))->toBeTrue();

    $country->restore();
    expect($this->catalog->active()->contains('id', $country->id))->toBeTrue();
});

test('uncommitted country changes do not contaminate the cache after rollback', function (): void {
    $country = Country::where('code', 'DZ')->firstOrFail();
    $original = $this->catalog->active()->find($country->id)->name_fr;
    $db = $country->getConnection();
    $db->beginTransaction();
    try {
        $country->name_fr = 'Uncommitted name';
        $country->save();
        expect($this->catalog->active()->find($country->id)->name_fr)->toBe('Uncommitted name');
    } finally {
        $db->rollBack();
    }

    expect($this->catalog->active()->find($country->id)->name_fr)->toBe($original);
});

test('tenant default cache settings cannot change the central reference cache', function (): void {
    $expected = $this->catalog->active()->modelKeys();
    $original = config('cache.stores.file.path');
    config(['cache.stores.file.path' => storage_path('framework/cache/tenant-test')]);
    $db = (new Country)->getConnection();
    $db->enableQueryLog();
    $db->flushQueryLog();
    try {
        expect($this->catalog->active()->modelKeys())->toBe($expected);
        expect($db->getQueryLog())->toBe([]);
    } finally {
        config(['cache.stores.file.path' => $original]);
        $db->disableQueryLog();
        $db->flushQueryLog();
    }
});
