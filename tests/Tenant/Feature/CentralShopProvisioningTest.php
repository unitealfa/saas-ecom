<?php

use App\Enums\Central\Deployments\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\Domain;
use App\Models\Central\Role;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\Tenant\Shop;
use App\Models\Tenant\User as ShopUser;
use App\Models\User;
use App\Policies\Central\TenantPolicy;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TestCase;

test('the durable central queue provisions two isolated MySQL shops with real owner links and retries safely', function (): void {
    Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
    $this->seed(AuthorizationSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));
    $owner = User::factory()->create(['legal_profile_version' => 1,
        'legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now(), 'legal_verified_by_id' => $admin->id]);
    $databases = [];

    try {
        foreach (['nour', 'amine'] as $slug) {
            $this->actingAs($admin, 'central')->post(route('central.users.tenants.store', $owner), [
                'shop_name' => ucfirst($slug), 'slug' => $slug, 'creation_key' => (string) Str::uuid(),
            ])->assertSessionHasNoErrors()->assertRedirect();
            $tenant = Tenant::where('slug', $slug)->firstOrFail();
            $databases[] = $tenant->database()->getName();
            $domain = $tenant->domains()->sole();
            expect(Str::isUuid($domain->uuid, version: 4))->toBeTrue()
                ->and($domain->tenant_id)->toBe($tenant->id)
                ->and($domain->verification_status)->toBe(VerificationStatusEnum::VERIFIED)
                ->and($domain->verified_at)->not->toBeNull();
        }
        expect(DB::table('jobs')->count())->toBe(2);
        expect(TenantSchemaDeployment::where('status', StatusEnum::PENDING)->count())->toBe(2);
        foreach ($databases as $database) {
            expect(DB::selectOne('SELECT COUNT(*) AS count FROM information_schema.schemata WHERE schema_name = ?', [$database])->count)->toBe(0);
        }

        Artisan::call('queue:work', ['connection' => 'central-provisioning', '--stop-when-empty' => true, '--no-interaction' => true]);
        expect(DB::table('jobs')->count())->toBe(0);
        expect(TenantSchemaDeployment::where('status', StatusEnum::SUCCEEDED)->count())->toBe(2);
        expect(DB::table('failed_jobs')->count())->toBe(0);
        foreach (Tenant::where('user_id', $owner->id)->get() as $tenant) {
            expect($tenant->status)->toBe(App\Enums\Central\Tenants\StatusEnum::ACTIVE);
            expect($tenant->schema_version)->not->toBeNull();
            $tenant->run(function () use ($tenant, $owner): void {
                expect(Shop::count())->toBe(1);
                expect(Shop::firstOrFail()->tenant_uuid)->toBe($tenant->uuid);
                expect(Shop::firstOrFail()->shop_name)->toBe($tenant->shop_name);
                expect(ShopUser::firstOrFail()->central_user_uuid)->toBe($owner->uuid);
                expect(ShopUser::firstOrFail()->password)->not->toBe($owner->password);
                expect(Shop::firstOrFail()->business_type)->toBe('OTHER');
                expect(Shop::firstOrFail()->cart_lifetime_days)->toBe(7);
            });
        }
        $deployment = TenantSchemaDeployment::firstOrFail();
        app()->call([new ProvisionTenantDatabase($deployment->id), 'handle']);
        expect($deployment->fresh()->attempt_number)->toBe(1);
    } finally {
        tenancy()->end();
        foreach ($databases as $database) {
            TestCase::dropIsolatedMysqlDatabase($database);
        }
    }
});

test('the configured tenant alias resolves its factory and inherits the central tenant policy', function (): void {
    Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
    Event::fake([TenantCreated::class]);

    $tenant = App\Models\Tenant::factory()->create();

    expect(config('tenancy.tenant_model'))->toBe(App\Models\Tenant::class)
        ->and($tenant->exists)->toBeTrue()
        ->and(Tenant::findOrFail($tenant->id)->uuid)->toBe($tenant->uuid)
        ->and(Gate::getPolicyFor(App\Models\Tenant::class))->toBeInstanceOf(TenantPolicy::class);
    Event::assertDispatched(TenantCreated::class);
});

test('domain verification fields cannot be mass assigned', function (string $field): void {
    $strict = Model::preventsSilentlyDiscardingAttributes();
    Model::preventSilentlyDiscardingAttributes();

    try {
        expect(fn () => (new Domain)->fill([$field => null]))->toThrow(MassAssignmentException::class);
    } finally {
        Model::preventSilentlyDiscardingAttributes($strict);
    }
})->with(['verification_status', 'verified_at', 'certificate_status']);
