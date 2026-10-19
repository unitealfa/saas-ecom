<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class);

beforeEach(function (): void {
    Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
});

function runCentralRace(array $operations): array
{
    $database = DB::connection()->getDatabaseName();
    expect($database)->toMatch('/^aydra_test_[a-f0-9]{12}_central_/');
    $script = <<<'PHP'
    require $argv[1].'/vendor/autoload.php';
    $app = require $argv[1].'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $database = $argv[2];
    if (! preg_match('/^aydra_test_[a-f0-9]{12}_central_[a-z0-9]+$/D', $database)) { exit(3); }
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'tenancy.database.central_connection' => 'mysql']);
    Illuminate\Support\Facades\DB::purge('mysql');
    echo "READY\n"; flush();
    if (trim(fgets(STDIN)) !== 'GO') { exit(4); }
    try {
        $db = Illuminate\Support\Facades\DB::connection();
        if ($db->getDatabaseName() !== $database) { exit(5); }
        if (str_starts_with($argv[5], 'shop-')) {
            $owner = App\Models\User::findOrFail((int) $argv[3]);
            $key = match ($argv[5]) {
                'shop-first' => '8e08c067-5cce-4fb0-bb90-984f63c27f42',
                'shop-add-a' => '8e08c067-5cce-4fb0-bb90-984f63c27f43',
                'shop-add-b' => '8e08c067-5cce-4fb0-bb90-984f63c27f44',
            };
            $data = new App\DTOs\Central\Tenants\TenantData($argv[5], $argv[5], $key,
                App\DTOs\Tenant\Shop\ShopSettings::fromArray([]));
            $creator = app(App\Services\Central\Tenants\TenantCreator::class);
            $argv[5] === 'shop-first' ? $creator->createFirst($owner, $data) : $creator->createOwned($owner, $data);
        } elseif (in_array($argv[5], ['approve', 'reject'], true)) {
            $actor = App\Models\User::findOrFail((int) $argv[4]);
            $target = App\Models\User::findOrFail((int) $argv[3]);
            app(App\Services\Central\Users\UserApprovalService::class)->decide($actor, $target,
                $argv[5] === 'approve', $argv[5] === 'reject' ? 'Dossier refusé pendant la concurrence.' : null,
                1, App\Enums\Central\Users\StatusEnum::PENDING);
        } elseif ($argv[5] === 'secondary') {
            $db->table('user_contacts')->insert(['uuid' => (string) Illuminate\Support\Str::uuid(), 'user_id' => (int) $argv[3],
                'country_id' => (int) $argv[4], 'kind' => 'phone', 'value' => '+213779107446', 'is_primary' => false]);
        } else {
            $db->table('users')->where('id', (int) $argv[3])->update(['phone' => '+213779107446', 'phone_country_id' => (int) $argv[4]]);
        }
        echo 'created';
    } catch (Illuminate\Database\UniqueConstraintViolationException|Illuminate\Validation\ValidationException) {
        echo 'conflict';
    } catch (Throwable $error) {
        fwrite(STDERR, get_class($error)); exit(2);
    }
    PHP;
    $processes = [];
    try {
        foreach ($operations as [$userId, $contextId, $operation]) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, '-r', $script, base_path(), $database, (string) $userId, (string) $contextId, $operation],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), null, ['bypass_shell' => true]);
            expect(is_resource($process))->toBeTrue();
            stream_set_timeout($pipes[1], 20);
            $processes[] = [$process, $pipes];
        }
        foreach ($processes as [, $pipes]) {
            expect(trim(fgets($pipes[1])))->toBe('READY');
        }
        foreach ($processes as [, $pipes]) {
            fwrite($pipes[0], "GO\n");
            fclose($pipes[0]);
        }
        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $results[] = stream_get_contents($pipes[1]);
            expect(stream_get_contents($pipes[2]))->toBe('');
            fclose($pipes[1]);
            fclose($pipes[2]);
            expect(proc_close($process))->toBe(0);
        }
        $processes = [];
        sort($results);
        return $results;
    } finally {
        foreach ($processes as [$process, $pipes]) {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) { fclose($pipe); }
            }
            if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        }
    }
}

test('two real MySQL clients cannot assign the same secondary and primary phone to different accounts', function (): void {
    $first = User::factory()->create(['phone' => '0555123456']);
    $second = User::factory()->create();
    expect(runCentralRace([[$first->id, $first->country_id, 'secondary'], [$second->id, $second->country_id, 'primary']]))
        ->toBe(['conflict', 'created']);
    expect(DB::table('user_contacts')->where('kind', 'phone')->where('value', '+213779107446')->count())->toBe(1);
});

test('two real approval decisions cannot overwrite each other or desynchronize account and dossier', function (): void {
    test()->seed(\Database\Seeders\Central\AuthorizationSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(\App\Models\Central\Role::findByName('root', 'central'));
    $merchant = User::factory()->create(['status' => \App\Enums\Central\Users\StatusEnum::PENDING,
        'legal_profile_version' => 1, 'legal_verification_status' => \App\Enums\VerificationStatusEnum::PENDING]);
    expect(runCentralRace([[$merchant->id, $admin->id, 'approve'], [$merchant->id, $admin->id, 'reject']]))
        ->toBe(['conflict', 'created']);
    $merchant->refresh();
    expect($merchant->legal_verification_status)->toBe($merchant->status === \App\Enums\Central\Users\StatusEnum::ACTIVE
        ? \App\Enums\VerificationStatusEnum::VERIFIED : \App\Enums\VerificationStatusEnum::FAILED);
    expect(\App\Models\Central\Activity::forSubject($merchant)->whereIn('event', ['central_account_approved', 'central_account_rejected'])->count())->toBe(1);
});

function raceApprovedOwner(): User
{
    test()->seed(\Database\Seeders\Central\AuthorizationSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(\App\Models\Central\Role::findByName('root', 'central'));

    return User::factory()->create(['legal_profile_version' => 1,
        'legal_verification_status' => \App\Enums\VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_verified_by_id' => $admin->id]);
}

test('two real first shop submissions with the same key reserve one shop and one job', function (): void {
    $owner = raceApprovedOwner();
    expect(runCentralRace([[$owner->id, $owner->country_id, 'shop-first'], [$owner->id, $owner->country_id, 'shop-first']]))
        ->toBe(['created', 'created']);
    expect(\App\Models\Central\Tenant::count())->toBe(1)->and(DB::table('jobs')->count())->toBe(1);
});

test('two real additional shop submissions cannot both take the last quota slot', function (): void {
    $owner = raceApprovedOwner();
    app(\App\Services\Central\Tenants\TenantCreator::class)->createFirst($owner, new \App\DTOs\Central\Tenants\TenantData(
        'Première', 'initial-shop', '8e08c067-5cce-4fb0-bb90-984f63c27f41', \App\DTOs\Tenant\Shop\ShopSettings::fromArray([]),
    ));
    expect(runCentralRace([[$owner->id, $owner->country_id, 'shop-add-a'], [$owner->id, $owner->country_id, 'shop-add-b']]))
        ->toBe(['conflict', 'created']);
    expect(\App\Models\Central\Tenant::count())->toBe(2)->and(DB::table('jobs')->count())->toBe(2);
});
