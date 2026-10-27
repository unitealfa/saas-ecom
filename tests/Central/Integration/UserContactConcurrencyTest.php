<?php

use App\DTOs\Central\Tenants\TenantData;
use App\DTOs\Tenant\Shop\ShopSettings;
use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Authorization\CentralPermissionAssignmentService;
use App\Services\Central\Tenants\TenantCreator;
use App\Services\Central\Users\UserManagementService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        if (str_starts_with($argv[5], 'permissions:')) {
            $actor = App\Models\User::findOrFail((int) $argv[4]);
            $target = App\Models\User::findOrFail((int) $argv[3]);
            $permission = App\Models\Central\Permissions\Permission::findByName('saas.users.view', 'central');
            app(App\Services\Central\Authorization\CentralPermissionAssignmentService::class)->update(
                $actor, $target, [['permission' => $permission->uuid, 'enabled' => false, 'days' => 9999]],
                substr($argv[5], strlen('permissions:')), null,
            );
        } elseif (str_starts_with($argv[5], 'shop-')) {
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
        } else {
            $db->table('users')->where('id', (int) $argv[3])->update(['phone' => '+213779107446', 'phone_country_id' => (int) $argv[4]]);
        }
        echo 'created';
    } catch (Illuminate\Database\UniqueConstraintViolationException|Illuminate\Validation\ValidationException) {
        echo 'conflict';
    } catch (Illuminate\Auth\Access\AuthorizationException $error) {
        if (! in_array($argv[5], ['approve', 'reject'], true)) {
            fwrite(STDERR, get_class($error)); exit(2);
        }
        // A committed approval can make the concurrent rejection forbidden.
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
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }
}

test('two real MySQL clients cannot assign the same phone to different accounts', function (): void {
    $first = User::factory()->create(['phone' => '0555123456']);
    $second = User::factory()->create();
    expect(runCentralRace([[$first->id, $first->country_id, 'primary'], [$second->id, $second->country_id, 'primary']]))
        ->toBe(['conflict', 'created']);
    expect(DB::table('users')->where('phone', '+213779107446')->count())->toBe(1);
});

test('two real approval decisions cannot overwrite each other or desynchronize account and dossier', function (): void {
    test()->seed(AuthorizationSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));
    $merchant = User::factory()->create(['status' => StatusEnum::PENDING,
        'legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::PENDING]);
    expect(runCentralRace([[$merchant->id, $admin->id, 'approve'], [$merchant->id, $admin->id, 'reject']]))
        ->toBe(['conflict', 'created']);
    $merchant->refresh();
    expect($merchant->legal_verification_status)->toBe($merchant->status === StatusEnum::ACTIVE
        ? VerificationStatusEnum::VERIFIED : VerificationStatusEnum::FAILED);
    expect(Activity::forSubject($merchant)->whereIn('event', ['central_account_approved', 'central_account_rejected'])->count())->toBe(1);
});

test('two real permission edits cannot overwrite the same displayed state', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));
    $staff = User::factory()->create();
    $staff->assignRole(Role::findByName('user-reader', 'central'));
    $version = app(CentralPermissionAssignmentService::class)->version($staff);
    expect(runCentralRace([[$staff->id, $admin->id, 'permissions:'.$version], [$staff->id, $admin->id, 'permissions:'.$version]]))
        ->toBe(['conflict', 'created']);
    expect($staff->fresh()->can('saas.users.view'))->toBeFalse()
        ->and(Activity::forSubject($staff)->where('event', 'central_staff_permissions_updated')->count())->toBe(1);
});

test('root row locks do not block unrelated mutations and committed revocations deny subsequent actions', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $admin = User::factory()->create();
    $root = Role::findByName('root', 'central');
    $admin->assignRole($root);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('concurrent-suspender', ['saas.users.suspend' => 9999]));
    $target = User::factory()->create();
    $nextTarget = User::factory()->create();
    $actor->load('roles', 'permissions');
    Gate::forUser($actor)->authorize('suspend', $target);
    $permission = Permission::findByName('saas.users.suspend', 'central');
    $database = DB::connection()->getDatabaseName();
    expect($database)->toMatch('/^aydra_test_[a-f0-9]{12}_central_/');
    $settings = config('database.connections.mysql');
    $revoker = new PDO('mysql:host='.$settings['host'].';port='.$settings['port'].';dbname='.$database.';charset=utf8mb4',
        $settings['username'], $settings['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $timeout = (int) DB::selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS value')->value;
    $service = app(UserManagementService::class);

    try {
        $revoker->beginTransaction();
        $lock = $revoker->prepare('SELECT id FROM roles WHERE id = ? FOR UPDATE');
        $lock->execute([$root->id]);
        $revoke = $revoker->prepare('DELETE FROM model_has_permissions WHERE permission_id = ? AND model_id = ? AND model_type = ?');
        $revoke->execute([$permission->id, $actor->id, 'central_user']);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        $service->changeStatus($actor, $target, StatusEnum::SUSPENDED);
        expect($target->fresh()->status)->toBe(StatusEnum::SUSPENDED);
        $revoker->commit();

        expect(fn () => $service->changeStatus($actor, $nextTarget, StatusEnum::SUSPENDED))
            ->toThrow(AuthorizationException::class);
        expect($nextTarget->fresh()->status)->toBe(StatusEnum::ACTIVE);
        expect(Activity::forSubject($nextTarget)->where('event', 'central_account_status_updated')->count())->toBe(0);
    } finally {
        if ($revoker->inTransaction()) {
            $revoker->rollBack();
        }
        DB::statement('SET SESSION innodb_lock_wait_timeout = '.$timeout);
    }
});

function raceApprovedOwner(): User
{
    test()->seed(AuthorizationSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));

    return User::factory()->create(['legal_profile_version' => 1,
        'legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_verified_by_id' => $admin->id]);
}

test('two real first shop submissions with the same key reserve one shop and one job', function (): void {
    $owner = raceApprovedOwner();
    expect(runCentralRace([[$owner->id, $owner->country_id, 'shop-first'], [$owner->id, $owner->country_id, 'shop-first']]))
        ->toBe(['created', 'created']);
    expect(Tenant::count())->toBe(1)->and(DB::table('jobs')->count())->toBe(1);
});

test('two real additional shop submissions cannot both take the last quota slot', function (): void {
    $owner = raceApprovedOwner();
    app(TenantCreator::class)->createFirst($owner, new TenantData(
        'Première', 'initial-shop', '8e08c067-5cce-4fb0-bb90-984f63c27f41', ShopSettings::fromArray([]),
    ));
    expect(runCentralRace([[$owner->id, $owner->country_id, 'shop-add-a'], [$owner->id, $owner->country_id, 'shop-add-b']]))
        ->toBe(['conflict', 'created']);
    expect(Tenant::count())->toBe(2)->and(DB::table('jobs')->count())->toBe(2);
});
