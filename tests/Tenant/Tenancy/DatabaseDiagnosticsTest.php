<?php

use App\Models\Tenant;
use Database\Seeders\Central\LocalDevelopmentSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    isolateLocalFixtureStorage();
    config(['app.debug' => true, 'tenancy.database.suffix' => '.sqlite', 'tenancy.saas_base_domain' => 'aydra.localhost']);
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
});

afterEach(function (): void {
    tenancy()->end();
    foreach (Tenant::get() as $tenant) {
        $name = $tenant->database()->getName();
        if (! preg_match('/^boutique_boutique[12]\.sqlite$/D', $name)) {
            throw new LogicException('Refusing to remove an unexpected fixture database.');
        }
        File::delete(database_path($name));
    }
    cleanLocalFixtureStorage();
});

test('local diagnostics show correct domains isolated shop ids and measured query counts', function (): void {
    Storage::fake('local');
    $this->seed(LocalDevelopmentSeeder::class);
    $this->get('http://aydra.localhost/_dev/database')->assertOk()
        ->assertSee('boutique1.aydra.localhost')->assertSee('boutique2.aydra.localhost')
        ->assertSee('boutique_boutique1.sqlite')->assertSee('boutique_boutique2.sqlite')
        ->assertDontSee('LocalTest!2026-Owner')->assertHeader('Cache-Control', 'no-store, private');

    foreach ([1, 2] as $number) {
        $tenant = Tenant::where('slug', 'boutique'.$number)->firstOrFail();
        $tenant->run(function (): void {
            DB::connection('tenant')->table('users')->whereNull('central_user_uuid')->update(['last_name' => '<script>alert(1)</script>']);
        });
        $other = $number === 1 ? 2 : 1;
        $this->get('http://boutique'.$number.'.aydra.localhost/_dev/database')->assertOk()
            ->assertSee('The id of the current tenant is '.$tenant->id)
            ->assertSee('boutique_boutique'.$number.'.sqlite')
            ->assertSee('BOUTIQUE'.$number.'-LOCAL-0050')
            ->assertDontSee('BOUTIQUE'.$other.'-LOCAL-')->assertDontSee('boutique'.$other.'@example.test')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertViewHas('measures', fn (array $rows): bool => $rows[0]['queries'] === 1 && $rows[0]['rows'] === 20 && $rows[1]['queries'] === 21 && $rows[2]['queries'] === 2);
        expect(tenancy()->initialized)->toBeFalse();
    }

    $this->app['env'] = 'production';
    $this->get('http://boutique1.aydra.localhost/_dev/database')->assertNotFound()->assertDontSee('boutique1@example.test');
});

test('central diagnostics are hidden outside the local debug loopback boundary', function (string $environment, bool $debug, string $ip): void {
    $this->app['env'] = $environment;
    config(['app.debug' => $debug]);
    $this->withServerVariables(['REMOTE_ADDR' => $ip])->get('http://aydra.localhost/_dev/database')
        ->assertNotFound()->assertDontSee('Comptes centraux');
})->with([
    'production' => ['production', true, '127.0.0.1'],
    'debug disabled' => ['local', false, '127.0.0.1'],
    'remote request' => ['local', true, '192.0.2.1'],
]);

test('unknown tenant hosts do not expose database diagnostics', function (): void {
    $this->get('http://unknown.aydra.localhost/_dev/database')->assertNotFound();
});
