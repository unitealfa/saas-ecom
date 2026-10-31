<?php

use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\FreePlanSeeder;
use Database\Seeders\Central\LocalAuthenticationSeeder;
use Database\Seeders\Central\LocalDevelopmentSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\TestCase;

pest()->extend(TestCase::class);

test('the default seeder orders existing seeders and limits demonstration data to local environments', function (string $environment, bool $fixtures): void {
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

    $expected = [CountrySeeder::class, FreePlanSeeder::class];
    if ($fixtures) {
        $expected[] = LocalDevelopmentSeeder::class;
    }
    $expected[] = AuthorizationSeeder::class;
    if ($fixtures) {
        $expected[] = LocalAuthenticationSeeder::class;
    }

    expect($seeder->calls)->toBe($expected);
})->with([
    'local' => ['local', true],
    'testing' => ['testing', true],
    'production' => ['production', false],
    'staging' => ['staging', false],
]);
