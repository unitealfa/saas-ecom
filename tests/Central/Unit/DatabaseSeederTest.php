<?php

use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\FreePlanSeeder;
use Database\Seeders\Central\GeographicAreaSeeder;
use Database\Seeders\Central\LocalAuthenticationSeeder;
use Database\Seeders\Central\LocalDevelopmentSeeder;
use Database\Seeders\Central\ShippingCarrierSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\TestCase;

pest()->extend(TestCase::class);

test('the demonstration account seeder skips production even when called directly', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    expect((new LocalDevelopmentSeeder)->run())->toBeNull();
});

test('central reference seeders refuse an initialized tenant context', function (string $class): void {
    tenancy()->initialized = true;
    try {
        expect(fn () => (new $class)->run())->toThrow(LogicException::class);
    } finally {
        tenancy()->initialized = false;
    }
})->with([DatabaseSeeder::class, CountrySeeder::class]);

test('the default seeder initializes authorization before demonstration accounts in every environment', function (string $environment): void {
    app()->detectEnvironment(fn (): string => $environment);

    $seeder = new class extends DatabaseSeeder
    {
        public array $calls = [];

        public function call($class, $silent = false, array $parameters = [])
        {
            array_push($this->calls, ...(array) $class);

            return $this;
        }
    };

    $seeder->run();

    $expected = [CountrySeeder::class, ShippingCarrierSeeder::class, GeographicAreaSeeder::class, FreePlanSeeder::class, AuthorizationSeeder::class];
    $expected[] = LocalDevelopmentSeeder::class;
    $expected[] = LocalAuthenticationSeeder::class;

    expect($seeder->calls)->toBe($expected);
})->with([
    'local' => ['local'],
    'testing' => ['testing'],
    'production' => ['production'],
    'staging' => ['staging'],
]);
